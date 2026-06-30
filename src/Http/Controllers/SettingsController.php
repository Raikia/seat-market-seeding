<?php

namespace Raikia\SeatMarketSeeding\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Raikia\SeatMarketSeeding\Helpers\SeatFittingPluginHelper;
use Raikia\SeatMarketSeeding\Jobs\RefreshMarketSeedingMarkets;
use Raikia\SeatMarketSeeding\Models\MarketSeedingItemSource;
use Raikia\SeatMarketSeeding\Models\MarketSeedingProfile;
use Raikia\SeatMarketSeeding\Models\MarketSeedingTargetHistory;
use Raikia\SeatMarketSeeding\Models\MarketSeedingTrackedDoctrine;
use Raikia\SeatMarketSeeding\Models\MarketStockDailySummary;
use Raikia\SeatMarketSeeding\Models\MarketStockHistory;
use Raikia\SeatMarketSeeding\Models\MarketStockSnapshot;
use Raikia\SeatMarketSeeding\Models\SeededMarket;
use Raikia\SeatMarketSeeding\Models\SeededMarketItem;
use Raikia\SeatMarketSeeding\Services\DoctrineTrackingSync;
use Raikia\SeatMarketSeeding\Services\MarketSeedingSettings;
use Raikia\SeatMarketSeeding\Services\SavedFittingSource;
use Raikia\SeatMarketSeeding\Services\StockListParser;
use Raikia\SeatMarketSeeding\Services\StockTargetPreviewer;
use Raikia\SeatMarketSeeding\Services\StockTargetImporter;
use Raikia\SeatMarketSeeding\Services\StockTargetProjector;
use Raikia\SeatMarketSeeding\Support\MarketSeedingCache;
use Seat\Eveapi\Models\Sde\InvType;
use Seat\Eveapi\Models\Sde\StaStation;
use Seat\Eveapi\Models\Universe\UniverseStructure;
use Seat\Web\Http\Controllers\Controller;

class SettingsController extends Controller
{
    public function index(SavedFittingSource $savedFittings, MarketSeedingSettings $settings)
    {
        $markets = SeededMarket::with('items.sources', 'items.type.group', 'role', 'trackedDoctrines.fitSettings')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $roles = \Seat\Web\Models\Acl\Role::all();
        $profiles = MarketSeedingProfile::orderBy('name')->get();
        $savedFittingsAvailable = $savedFittings->isAvailable();
        $seatFittingAvailable = SeatFittingPluginHelper::pluginIsAvailable();
        $historyRetentionDays = $settings->historyRetentionDays();
        $jitaPriceRefreshMinutes = $settings->jitaPriceRefreshMinutes();
        $recommendationSalesDays = $settings->recommendationSalesDays();
        $recommendationBufferPercentage = $settings->recommendationBufferPercentage();

        return view('seat-market-seeding::settings', compact('markets', 'roles', 'profiles', 'savedFittingsAvailable', 'seatFittingAvailable', 'historyRetentionDays', 'jitaPriceRefreshMinutes', 'recommendationSalesDays', 'recommendationBufferPercentage'));
    }

    public function updateGeneralSettings(Request $request, MarketSeedingSettings $settings)
    {
        $data = $request->validate([
            'history_retention_days' => 'required|integer|min:1|max:3650',
            'jita_price_refresh_minutes' => 'required|integer|min:5|max:10080',
            'recommendation_sales_days' => 'required|integer|min:1|max:365',
            'recommendation_buffer_percentage' => 'required|integer|min:0|max:500',
        ]);

        $settings->setHistoryRetentionDays((int) $data['history_retention_days']);
        $settings->setJitaPriceRefreshMinutes((int) $data['jita_price_refresh_minutes']);
        $settings->setRecommendationSalesDays((int) $data['recommendation_sales_days']);
        $settings->setRecommendationBufferPercentage((int) $data['recommendation_buffer_percentage']);

        return redirect()->route('market-seeding.settings')->with('success', 'Market seeding settings updated successfully.');
    }

    public function clearHistory()
    {
        $count = MarketStockHistory::query()->count()
            + MarketStockSnapshot::query()->count()
            + MarketStockDailySummary::query()->count();

        MarketStockHistory::query()->delete();
        MarketStockSnapshot::query()->delete();
        MarketStockDailySummary::query()->delete();
        MarketSeedingCache::bumpHistoryPriceVersion();

        return redirect()->route('market-seeding.settings')->with('success', $count . ' stock history entr' . ($count === 1 ? 'y was' : 'ies were') . ' cleared.');
    }

    public function clearAuditHistory()
    {
        $count = MarketSeedingTargetHistory::query()->count();

        MarketSeedingTargetHistory::query()->delete();

        return redirect()->route('market-seeding.settings')->with('success', $count . ' target audit entr' . ($count === 1 ? 'y was' : 'ies were') . ' cleared.');
    }

    public function storeMarket(Request $request)
    {
        $data = $request->validate($this->marketRules());

        $data = $this->normalizeMarketData($data);
        $data['sort_order'] = ((int) SeededMarket::max('sort_order')) + 10;
        SeededMarket::create($data);

        return redirect()->route('market-seeding.settings')->with('success', 'Market created successfully.');
    }

    public function updateMarket(Request $request, SeededMarket $market)
    {
        $data = $request->validate($this->marketRules());

        $market->update($this->normalizeMarketData($data));

        return redirect()->route('market-seeding.settings')->with('success', 'Market updated successfully.');
    }

    public function moveMarket(Request $request, SeededMarket $market)
    {
        $data = $request->validate([
            'direction' => 'required|in:up,down',
        ]);

        $this->normalizeMarketSortOrder();
        $market->refresh();

        $neighbor = SeededMarket::query()
            ->when($data['direction'] === 'up', function ($query) use ($market) {
                $query->where('sort_order', '<', $market->sort_order)
                    ->orderByDesc('sort_order');
            })
            ->when($data['direction'] === 'down', function ($query) use ($market) {
                $query->where('sort_order', '>', $market->sort_order)
                    ->orderBy('sort_order');
            })
            ->first();

        if ($neighbor) {
            $currentOrder = $market->sort_order;

            $market->update(['sort_order' => $neighbor->sort_order]);
            $neighbor->update(['sort_order' => $currentOrder]);
        }

        return redirect()->route('market-seeding.settings')->with('success', 'Market order updated successfully.');
    }

    public function destroyMarket(SeededMarket $market)
    {
        $market->delete();

        return redirect()->route('market-seeding.settings')->with('success', 'Market deleted successfully.');
    }

    public function storeProfile(Request $request)
    {
        MarketSeedingProfile::create($request->validate($this->profileRules()));

        return redirect()->route('market-seeding.settings')->with('success', 'Market profile created successfully.');
    }

    public function updateProfile(Request $request, MarketSeedingProfile $profile)
    {
        $profile->update($request->validate($this->profileRules()));

        return redirect()->route('market-seeding.settings')->with('success', 'Market profile updated successfully.');
    }

    public function destroyProfile(MarketSeedingProfile $profile)
    {
        $profile->delete();

        return redirect()->route('market-seeding.settings')->with('success', 'Market profile deleted successfully.');
    }

    public function storeItem(Request $request, SeededMarket $market, StockTargetProjector $projector)
    {
        $data = $request->validate([
            'type_id' => 'required|integer',
            'desired_quantity' => 'required|integer|min:1',
            'warning_percentage' => 'required|integer|min:0|max:100',
            'notes' => 'nullable|string',
            'keep_higher_quantity' => 'nullable|boolean',
        ]);

        $type = InvType::where('typeID', $data['type_id'])->firstOrFail();
        $hadItem = $market->items()->where('type_id', $type->typeID)->exists();

        $existing = $market->itemSources()
            ->where('source_type', MarketSeedingItemSource::SOURCE_MANUAL)
            ->where('type_id', $type->typeID)
            ->first();
        $quantity = ($data['keep_higher_quantity'] ?? false) && $existing
            ? max((int) $existing->quantity, (int) $data['desired_quantity'])
            : (int) optional($existing)->quantity + (int) $data['desired_quantity'];
        $item = $projector->setManualTarget(
            $market,
            (int) $type->typeID,
            $type->typeName,
            $quantity,
            $this->warningQuantityFromPercentage($quantity, (int) $data['warning_percentage']),
            $data['notes'] ?? null
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Item saved successfully.',
                'created' => !$hadItem,
                'item' => $this->itemPayload($item),
            ]);
        }

        return redirect()->route('market-seeding.settings')->with('success', 'Item saved successfully.');
    }

    public function storeTrackedDoctrine(Request $request, SeededMarket $market, DoctrineTrackingSync $sync)
    {
        $data = $request->validate($this->trackedDoctrineRules());

        if (!$sync->isAvailable()) {
            abort(404);
        }

        $doctrine = SeatFittingPluginHelper::getDoctrineWithFittings((int) $data['doctrine_id']);

        if (!$doctrine) {
            return redirect()->route('market-seeding.settings')->with('error', 'The selected doctrine could not be found.');
        }

        $trackedDoctrine = $market->trackedDoctrines()->updateOrCreate([
            'doctrine_id' => (int) $data['doctrine_id'],
        ], [
            'doctrine_name' => $doctrine->name,
            'multiplier' => (int) $data['multiplier'],
            'warning_percentage' => (int) $data['warning_percentage'],
            'merge_mode' => $data['merge_mode'],
            'fit_aggregation_mode' => $data['fit_aggregation_mode'],
        ]);

        $sync->syncFitSettings($trackedDoctrine, $this->doctrineFitSettingsFromRequest($data));
        $sync->syncDoctrine($trackedDoctrine);

        if ($request->expectsJson()) {
            return response()->json($this->trackedDoctrinePayload($market->fresh('trackedDoctrines'), 'Doctrine tracking updated successfully.'));
        }

        return redirect()->route('market-seeding.settings')->with('success', 'Doctrine tracking updated successfully.');
    }

    public function previewTrackedDoctrine(Request $request, SeededMarket $market, DoctrineTrackingSync $sync, StockTargetPreviewer $previewer)
    {
        $data = $request->validate($this->trackedDoctrineRules());

        if (!$sync->isAvailable()) {
            abort(404);
        }

        $doctrine = SeatFittingPluginHelper::getDoctrineWithFittings((int) $data['doctrine_id']);

        if (!$doctrine) {
            return response()->json([
                'message' => 'The selected doctrine could not be found.',
            ], 422);
        }

        $submittedFitSettings = $this->doctrineFitSettingsFromRequest($data);

        if (!$submittedFitSettings) {
            $submittedFitSettings = $this->storedDoctrineFitSettings($market, (int) $data['doctrine_id']);
        }

        $doctrinePreview = $sync->doctrinePreview(
            (int) $data['doctrine_id'],
            (int) $data['multiplier'],
            $submittedFitSettings,
            $data['fit_aggregation_mode']
        );
        $preview = $previewer->previewDoctrine(
            $market,
            (int) $data['doctrine_id'],
            $doctrine->name,
            $doctrinePreview['items'],
            (int) $data['warning_percentage'],
            $data['merge_mode']
        );
        $preview['doctrine'] = [
            'fits' => $doctrinePreview['fits'],
            'fit_aggregation_mode' => $data['fit_aggregation_mode'],
        ];

        return response()->json($preview);
    }

    public function updateTrackedDoctrine(Request $request, MarketSeedingTrackedDoctrine $trackedDoctrine, DoctrineTrackingSync $sync)
    {
        $data = $request->validate([
            'multiplier' => 'required|integer|min:1|max:10000',
            'warning_percentage' => 'required|integer|min:0|max:100',
            'merge_mode' => 'required|in:max,add',
            'fit_aggregation_mode' => 'nullable|in:sum,max',
            'doctrine_fit_settings' => 'nullable|string',
        ]);

        $data['fit_aggregation_mode'] = $data['fit_aggregation_mode'] ?? MarketSeedingTrackedDoctrine::FIT_AGGREGATION_MAX;
        $trackedDoctrine->update([
            'multiplier' => (int) $data['multiplier'],
            'warning_percentage' => (int) $data['warning_percentage'],
            'merge_mode' => $data['merge_mode'],
            'fit_aggregation_mode' => $data['fit_aggregation_mode'],
        ]);
        $submittedFitSettings = $this->doctrineFitSettingsFromRequest($data);

        if ($submittedFitSettings) {
            $sync->syncFitSettings($trackedDoctrine, $submittedFitSettings);
        }

        $sync->syncDoctrine($trackedDoctrine);

        if ($request->expectsJson()) {
            return response()->json($this->trackedDoctrinePayload($trackedDoctrine->market->fresh('trackedDoctrines'), 'Doctrine tracking updated successfully.'));
        }

        return redirect()->route('market-seeding.settings')->with('success', 'Doctrine tracking updated successfully.');
    }

    public function destroyTrackedDoctrine(Request $request, MarketSeedingTrackedDoctrine $trackedDoctrine, StockTargetProjector $projector)
    {
        $market = $trackedDoctrine->market;

        DB::transaction(function () use ($trackedDoctrine, $market, $projector) {
            $trackedDoctrine->delete();

            if ($market) {
                $projector->recalculateMarket($market, MarketSeedingTargetHistory::CHANGE_DOCTRINE);
            }
        }, 5);

        if ($request->expectsJson()) {
            return response()->json($this->trackedDoctrinePayload($market->fresh('trackedDoctrines'), 'Doctrine tracking removed successfully.'));
        }

        return redirect()->route('market-seeding.settings')->with('success', 'Doctrine tracking removed successfully.');
    }

    public function importItems(Request $request, SeededMarket $market, StockListParser $parser, StockTargetImporter $importer)
    {
        $data = $request->validate([
            'stock_list' => 'required|string',
            'multiplier' => 'nullable|integer|min:1|max:10000',
            'warning_percentage' => 'required|integer|min:0|max:100',
            'mode' => 'required|in:add,replace',
            'keep_higher_quantity' => 'nullable|boolean',
        ]);

        $parsed = $parser->parseWithReport($data['stock_list'], (int) ($data['multiplier'] ?? 1));
        $count = $importer->import(
            $market,
            $parsed['items'],
            $data['mode'],
            (bool) ($data['keep_higher_quantity'] ?? false),
            (int) $data['warning_percentage'],
            MarketSeedingTargetHistory::CHANGE_BULK_IMPORT
        );

        if ($request->expectsJson()) {
            return response()->json($this->importPayload($market, $count, 'stock line(s) imported successfully.', $parsed['validation']));
        }

        return redirect()->route('market-seeding.settings')->with('success', $count . ' stock line(s) imported successfully.');
    }

    public function clearMarketItems(Request $request, SeededMarket $market)
    {
        DB::transaction(function () use ($market) {
            $market->items()->get()->each(function (SeededMarketItem $item) use ($market) {
                MarketSeedingTargetHistory::create([
                    'market_id' => $market->id,
                    'item_id' => $item->id,
                    'type_id' => $item->type_id,
                    'market_name' => $market->name,
                    'location_name' => $market->location_name,
                    'type_name' => $item->type_name,
                    'old_target_quantity' => (int) $item->desired_quantity,
                    'new_target_quantity' => 0,
                    'old_warning_quantity' => (int) $item->warning_quantity,
                    'new_warning_quantity' => 0,
                    'change_type' => MarketSeedingTargetHistory::CHANGE_CLEAR,
                    'user_id' => optional(auth()->user())->id,
                    'user_name' => $this->targetHistoryActorName(),
                ]);
            });
            $market->trackedDoctrines()->delete();
            $market->itemSources()->delete();
            $market->items()->delete();
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'All tracked items were cleared from ' . $market->name . '.',
                'tracked_count' => 0,
                'items' => [],
            ]);
        }

        return redirect()->route('market-seeding.settings')->with('success', 'All tracked items were cleared from ' . $market->name . '.');
    }

    public function previewItems(Request $request, SeededMarket $market, StockListParser $parser, StockTargetPreviewer $previewer)
    {
        $data = $request->validate([
            'stock_list' => 'required|string',
            'multiplier' => 'nullable|integer|min:1|max:10000',
            'warning_percentage' => 'required|integer|min:0|max:100',
            'mode' => 'required|in:add,replace',
            'keep_higher_quantity' => 'nullable|boolean',
        ]);

        $parsed = $parser->parseWithReport($data['stock_list'], (int) ($data['multiplier'] ?? 1));

        $preview = $previewer->preview(
            $market,
            $parsed['items'],
            $data['mode'],
            (bool) ($data['keep_higher_quantity'] ?? false),
            (int) $data['warning_percentage']
        );
        $preview['validation'] = $parsed['validation'];

        return response()->json($preview);
    }

    public function importSavedFitting(Request $request, SeededMarket $market, SavedFittingSource $savedFittings, StockTargetImporter $importer)
    {
        $data = $request->validate([
            'saved_fitting' => 'required|string',
            'multiplier' => 'nullable|integer|min:1|max:10000',
            'warning_percentage' => 'required|integer|min:0|max:100',
            'mode' => 'required|in:add,replace',
            'keep_higher_quantity' => 'nullable|boolean',
        ]);

        [$source, $sourceId] = array_pad(explode(':', $data['saved_fitting'], 2), 2, null);
        $items = $savedFittings->items($source, (int) $sourceId, (int) ($data['multiplier'] ?? 1));
        $count = $importer->import(
            $market,
            $items,
            $data['mode'],
            (bool) ($data['keep_higher_quantity'] ?? false),
            (int) $data['warning_percentage'],
            MarketSeedingTargetHistory::CHANGE_SAVED_FITTING
        );

        if ($request->expectsJson()) {
            return response()->json($this->importPayload($market, $count, 'saved fitting item(s) imported successfully.'));
        }

        return redirect()->route('market-seeding.settings')->with('success', $count . ' saved fitting item(s) imported successfully.');
    }

    public function previewSavedFitting(Request $request, SeededMarket $market, SavedFittingSource $savedFittings, StockTargetPreviewer $previewer)
    {
        $data = $request->validate([
            'saved_fitting' => 'required|string',
            'multiplier' => 'nullable|integer|min:1|max:10000',
            'warning_percentage' => 'required|integer|min:0|max:100',
            'mode' => 'required|in:add,replace',
            'keep_higher_quantity' => 'nullable|boolean',
        ]);

        [$source, $sourceId] = array_pad(explode(':', $data['saved_fitting'], 2), 2, null);
        $items = $savedFittings->items($source, (int) $sourceId, (int) ($data['multiplier'] ?? 1));

        $preview = $previewer->preview(
            $market,
            $items,
            $data['mode'],
            (bool) ($data['keep_higher_quantity'] ?? false),
            (int) $data['warning_percentage']
        );
        $preview['validation'] = $this->savedFittingValidation(count($items));

        return response()->json($preview);
    }

    public function refreshMarkets()
    {
        RefreshMarketSeedingMarkets::dispatch(optional(auth()->user())->main_character_id);

        return redirect()->route('market-seeding.settings')->with('success', 'Market refresh job queued. The dashboard will update as the worker processes it.');
    }

    public function updateItem(Request $request, SeededMarketItem $item, StockTargetProjector $projector)
    {
        $data = $request->validate([
            'desired_quantity' => 'required|integer|min:1',
            'warning_quantity' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $item = $projector->setEffectiveTarget(
            $item,
            (int) $data['desired_quantity'],
            array_key_exists('warning_quantity', $data) ? (int) $data['warning_quantity'] : null,
            $data['notes'] ?? null,
            MarketSeedingTargetHistory::CHANGE_MANUAL
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Item updated successfully.',
                'item' => $this->itemPayload($item->fresh()),
            ]);
        }

        return redirect()->route('market-seeding.settings')->with('success', 'Item updated successfully.');
    }

    public function destroyItem(Request $request, SeededMarketItem $item, StockTargetProjector $projector)
    {
        $market = $item->market;
        $remainingItem = $projector->removeManualTarget($item);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $remainingItem
                    ? 'Manual target removed. This item is still tracked by a doctrine.'
                    : 'Item removed successfully.',
                'item_id' => $item->id,
                'item' => $remainingItem ? $this->itemPayload($remainingItem) : null,
                'tracked_count' => $market ? $market->items()->count() : null,
            ]);
        }

        return redirect()->route('market-seeding.settings')->with('success', 'Item removed successfully.');
    }

    public function searchItems(Request $request)
    {
        $query = trim((string) $request->input('q', ''));

        if (strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $escaped = $this->escapeLike($query);
        $results = InvType::where('typeName', 'like', '%' . $escaped . '%')
            ->where('published', true)
            ->whereNotNull('marketGroupID')
            ->orderBy('typeName')
            ->limit(25)
            ->get()
            ->map(function ($type) {
                return [
                    'id' => $type->typeID,
                    'text' => $type->typeName,
                ];
            });

        return response()->json(['results' => $results]);
    }

    public function searchLocations(Request $request)
    {
        $query = trim((string) $request->input('q', ''));

        if (strlen($query) < 3) {
            return response()->json(['results' => []]);
        }

        $escaped = $this->escapeLike($query);

        $stations = StaStation::where('stationName', 'like', '%' . $escaped . '%')
            ->orderBy('stationName')
            ->limit(15)
            ->get()
            ->map(function ($station) {
                return [
                    'id' => $station->getRawOriginal('stationID'),
                    'text' => $station->getRawOriginal('stationName'),
                    'region_id' => $station->getRawOriginal('regionID'),
                    'solar_system_id' => $station->getRawOriginal('solarSystemID'),
                    'is_structure' => false,
                ];
            });

        $structures = UniverseStructure::where('name', 'like', '%' . $escaped . '%')
            ->orderBy('name')
            ->limit(15)
            ->get()
            ->map(function ($structure) {
                return [
                    'id' => $structure->structure_id,
                    'text' => $structure->name,
                    'region_id' => 10000002,
                    'solar_system_id' => $structure->solar_system_id,
                    'is_structure' => true,
                ];
            });

        return response()->json([
            'results' => collect($stations->all())
                ->merge($structures->all())
                ->values(),
        ]);
    }

    public function searchSavedFittings(Request $request, SavedFittingSource $savedFittings)
    {
        return response()->json([
            'results' => $savedFittings->search((string) $request->input('q', '')),
        ]);
    }

    public function searchDoctrines(Request $request, SavedFittingSource $savedFittings)
    {
        $results = collect($savedFittings->searchDoctrines((string) $request->input('q', '')));
        $marketId = (int) $request->input('market_id');

        if ($marketId > 0) {
            $trackedDoctrineIds = SeededMarket::find($marketId)
                ?->trackedDoctrines()
                ->pluck('doctrine_id')
                ->map(fn ($doctrineId) => (int) $doctrineId)
                ->all() ?: [];

            if ($trackedDoctrineIds) {
                $results = $results->reject(fn ($result) => in_array((int) $result['id'], $trackedDoctrineIds, true));
            }
        }

        return response()->json([
            'results' => $results->values(),
        ]);
    }

    private function marketRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'location_id' => 'required|integer',
            'location_name' => 'required|string|max:255',
            'region_id' => 'nullable|integer',
            'solar_system_id' => 'nullable|integer',
            'is_structure' => 'nullable|boolean',
            'role_id' => 'nullable|integer',
            'notes' => 'nullable|string',
        ];
    }

    private function profileRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'stock_list' => 'required|string',
        ];
    }

    private function trackedDoctrineRules(): array
    {
        return [
            'doctrine_id' => 'required|integer',
            'multiplier' => 'required|integer|min:1|max:10000',
            'warning_percentage' => 'required|integer|min:0|max:100',
            'merge_mode' => 'required|in:max,add',
            'fit_aggregation_mode' => 'required|in:sum,max',
            'doctrine_fit_settings' => 'nullable|string',
        ];
    }

    private function normalizeMarketData(array $data): array
    {
        $data['region_id'] = $data['region_id'] ?: 10000002;
        $data['is_structure'] = (bool) ($data['is_structure'] ?? false);
        $data['role_id'] = $data['role_id'] ?: null;

        return $data;
    }

    private function normalizeMarketSortOrder(): void
    {
        SeededMarket::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->each(function (SeededMarket $market, int $index) {
                $desiredOrder = ($index + 1) * 10;

                if ($market->sort_order !== $desiredOrder) {
                    $market->update(['sort_order' => $desiredOrder]);
                }
            });
    }

    private function itemPayload(SeededMarketItem $item): array
    {
        $item->loadMissing('sources', 'type.group');
        $sourceFlags = $item->sourceFlags();

        return [
            'id' => $item->id,
            'type_name' => $item->type_name,
            'type_category' => $item->typeCategoryName(),
            'desired_quantity' => $item->desired_quantity,
            'warning_quantity' => $item->warning_quantity,
            'source_flags' => $sourceFlags,
            'source_icons_html' => view('seat-market-seeding::partials.source-icons', compact('sourceFlags'))->render(),
            'update_url' => route('market-seeding.items.update', $item->id),
            'destroy_url' => route('market-seeding.items.destroy', $item->id),
            'history_url' => route('market-seeding.items.history', $item->id),
        ];
    }

    private function importPayload(SeededMarket $market, int $count, string $message, ?array $validation = null): array
    {
        $market->load(['items' => function ($query) {
            $query->with('sources', 'type.group')->orderBy('type_name');
        }]);

        return [
            'message' => $count . ' ' . $message,
            'tracked_count' => $market->items->count(),
            'validation' => $validation,
            'items' => $market->items->map(function (SeededMarketItem $item) {
                return $this->itemPayload($item);
            })->values(),
        ];
    }

    private function trackedDoctrinePayload(SeededMarket $market, string $message): array
    {
        $market->load(['trackedDoctrines.fitSettings', 'items' => function ($query) {
            $query->with('sources', 'type.group')->orderBy('type_name');
        }]);

        return [
            'message' => $message,
            'summary_html' => view('seat-market-seeding::partials.tracked-doctrine-summary', compact('market'))->render(),
            'list_html' => view('seat-market-seeding::partials.tracked-doctrine-list', compact('market'))->render(),
            'tracked_doctrines_count' => $market->trackedDoctrines->count(),
            'tracked_count' => $market->items->count(),
            'items' => $market->items->map(function (SeededMarketItem $item) {
                return $this->itemPayload($item);
            })->values(),
        ];
    }

    private function warningQuantityFromPercentage(int $quantity, int $percentage): int
    {
        $percentage = max(0, min(100, $percentage));

        return max(0, (int) ceil(max(1, $quantity) * ($percentage / 100)));
    }

    private function targetHistoryActorName(): string
    {
        $user = auth()->user();

        if (!$user) {
            return 'System';
        }

        return $user->name
            ?? $user->username
            ?? optional($user->main_character)->name
            ?? ('User #' . $user->id);
    }

    private function doctrineFitSettingsFromRequest(array $data): array
    {
        if (empty($data['doctrine_fit_settings'])) {
            return [];
        }

        $settings = json_decode(html_entity_decode($data['doctrine_fit_settings'], ENT_QUOTES, 'UTF-8'), true);

        if (!is_array($settings)) {
            return [];
        }

        return collect($settings)
            ->map(function ($setting) {
                return [
                    'fitting_id' => (int) ($setting['fitting_id'] ?? 0),
                    'ship_multiplier' => max(0, min(10000, (int) ($setting['ship_multiplier'] ?? 0))),
                    'fitting_multiplier' => max(0, min(10000, (int) ($setting['fitting_multiplier'] ?? 0))),
                ];
            })
            ->filter(fn ($setting) => $setting['fitting_id'] > 0)
            ->values()
            ->all();
    }

    private function storedDoctrineFitSettings(SeededMarket $market, int $doctrineId): array
    {
        $trackedDoctrine = $market->trackedDoctrines()
            ->with('fitSettings')
            ->where('doctrine_id', $doctrineId)
            ->first();

        if (!$trackedDoctrine) {
            return [];
        }

        return $trackedDoctrine->fitSettings
            ->map(function ($fitSetting) {
                return [
                    'fitting_id' => (int) $fitSetting->fitting_id,
                    'ship_multiplier' => (int) $fitSetting->ship_multiplier,
                    'fitting_multiplier' => (int) $fitSetting->fitting_multiplier,
                ];
            })
            ->values()
            ->all();
    }

    private function savedFittingValidation(int $itemCount): array
    {
        return [
            'processed_lines' => $itemCount,
            'ignored_lines' => 0,
            'valid_lines' => $itemCount,
            'skipped_lines' => $itemCount > 0 ? 0 : 1,
            'skipped' => $itemCount > 0 ? [] : [[
                'line' => 'Saved fitting source',
                'line_number' => null,
                'reason' => 'No importable items were found in this saved source.',
            ]],
        ];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
