<?php

namespace Raikia\SeatMarketSeeding\Http\Controllers;

use Illuminate\Http\Request;
use Raikia\SeatMarketSeeding\Models\SeededMarket;
use Raikia\SeatMarketSeeding\Models\SeededMarketItem;
use Raikia\SeatMarketSeeding\Services\EsiMarketOrderRefresh;
use Raikia\SeatMarketSeeding\Services\SavedFittingSource;
use Raikia\SeatMarketSeeding\Services\StockListParser;
use Raikia\SeatMarketSeeding\Services\StockTargetImporter;
use Seat\Eveapi\Models\RefreshToken;
use Seat\Eveapi\Models\Sde\InvType;
use Seat\Eveapi\Models\Sde\StaStation;
use Seat\Eveapi\Models\Universe\UniverseStructure;
use Seat\Web\Http\Controllers\Controller;

class SettingsController extends Controller
{
    public function index(SavedFittingSource $savedFittings)
    {
        $markets = SeededMarket::with('items', 'role')
            ->orderBy('name')
            ->get();
        $roles = \Seat\Web\Models\Acl\Role::all();
        $savedFittingsAvailable = $savedFittings->isAvailable();

        return view('seat-market-seeding::settings', compact('markets', 'roles', 'savedFittingsAvailable'));
    }

    public function storeMarket(Request $request)
    {
        $data = $request->validate($this->marketRules());

        $data = $this->normalizeMarketData($data);
        SeededMarket::create($data);

        return redirect()->route('market-seeding.settings')->with('success', 'Market created successfully.');
    }

    public function updateMarket(Request $request, SeededMarket $market)
    {
        $data = $request->validate($this->marketRules());

        $market->update($this->normalizeMarketData($data));

        return redirect()->route('market-seeding.settings')->with('success', 'Market updated successfully.');
    }

    public function destroyMarket(SeededMarket $market)
    {
        $market->delete();

        return redirect()->route('market-seeding.settings')->with('success', 'Market deleted successfully.');
    }

    public function storeItem(Request $request, SeededMarket $market)
    {
        $data = $request->validate([
            'type_id' => 'required|integer',
            'desired_quantity' => 'required|integer|min:1',
            'warning_quantity' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'keep_higher_quantity' => 'nullable|boolean',
        ]);

        $type = InvType::where('typeID', $data['type_id'])->firstOrFail();

        $item = $market->items()->firstOrNew(['type_id' => $type->typeID]);
        $item->type_name = $type->typeName;
        $item->desired_quantity = ($data['keep_higher_quantity'] ?? false) && $item->exists
            ? max((int) $item->desired_quantity, (int) $data['desired_quantity'])
            : (int) $item->desired_quantity + (int) $data['desired_quantity'];
        $item->warning_quantity = $data['warning_quantity'] ?: ($item->warning_quantity ?: $item->desired_quantity);
        $item->notes = $data['notes'] ?? $item->notes;
        $item->save();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Item saved successfully.',
                'created' => $item->wasRecentlyCreated,
                'item' => $this->itemPayload($item),
            ]);
        }

        return redirect()->route('market-seeding.settings')->with('success', 'Item saved successfully.');
    }

    public function importItems(Request $request, SeededMarket $market, StockListParser $parser, StockTargetImporter $importer)
    {
        $data = $request->validate([
            'stock_list' => 'required|string',
            'multiplier' => 'nullable|integer|min:1|max:10000',
            'mode' => 'required|in:add,replace',
            'keep_higher_quantity' => 'nullable|boolean',
        ]);

        $items = $parser->parse($data['stock_list'], (int) ($data['multiplier'] ?? 1));
        $count = $importer->import($market, $items, $data['mode'], (bool) ($data['keep_higher_quantity'] ?? false));

        if ($request->expectsJson()) {
            return response()->json($this->importPayload($market, $count, 'stock line(s) imported successfully.'));
        }

        return redirect()->route('market-seeding.settings')->with('success', $count . ' stock line(s) imported successfully.');
    }

    public function importSavedFitting(Request $request, SeededMarket $market, SavedFittingSource $savedFittings, StockTargetImporter $importer)
    {
        $data = $request->validate([
            'saved_fitting' => 'required|string',
            'multiplier' => 'nullable|integer|min:1|max:10000',
            'mode' => 'required|in:add,replace',
            'keep_higher_quantity' => 'nullable|boolean',
        ]);

        [$source, $sourceId] = array_pad(explode(':', $data['saved_fitting'], 2), 2, null);
        $items = $savedFittings->items($source, (int) $sourceId, (int) ($data['multiplier'] ?? 1));
        $count = $importer->import($market, $items, $data['mode'], (bool) ($data['keep_higher_quantity'] ?? false));

        if ($request->expectsJson()) {
            return response()->json($this->importPayload($market, $count, 'saved fitting item(s) imported successfully.'));
        }

        return redirect()->route('market-seeding.settings')->with('success', $count . ' saved fitting item(s) imported successfully.');
    }

    public function refreshMarket(SeededMarket $market, EsiMarketOrderRefresh $refresh)
    {
        $refreshToken = optional(auth()->user())->main_character_id
            ? RefreshToken::find(auth()->user()->main_character_id)
            : null;

        try {
            $count = $refresh->refresh($market, $refreshToken);
        } catch (\Throwable $e) {
            return redirect()->route('market-seeding.settings')->with('error', 'Market refresh failed: ' . $e->getMessage());
        }

        return redirect()->route('market-seeding.settings')->with('success', 'Market refresh completed. ' . $count . ' order(s) updated.');
    }

    public function updateItem(Request $request, SeededMarketItem $item)
    {
        $data = $request->validate([
            'desired_quantity' => 'required|integer|min:1',
            'warning_quantity' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $item->update([
            'desired_quantity' => $data['desired_quantity'],
            'warning_quantity' => $data['warning_quantity'] ?: $data['desired_quantity'],
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('market-seeding.settings')->with('success', 'Item updated successfully.');
    }

    public function destroyItem(SeededMarketItem $item)
    {
        $item->delete();

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

    private function normalizeMarketData(array $data): array
    {
        $data['region_id'] = $data['region_id'] ?: 10000002;
        $data['is_structure'] = (bool) ($data['is_structure'] ?? false);
        $data['role_id'] = $data['role_id'] ?: null;

        return $data;
    }

    private function itemPayload(SeededMarketItem $item): array
    {
        return [
            'id' => $item->id,
            'type_name' => $item->type_name,
            'desired_quantity' => $item->desired_quantity,
            'warning_quantity' => $item->warning_quantity,
            'update_url' => route('market-seeding.items.update', $item->id),
            'destroy_url' => route('market-seeding.items.destroy', $item->id),
        ];
    }

    private function importPayload(SeededMarket $market, int $count, string $message): array
    {
        $market->load(['items' => function ($query) {
            $query->orderBy('type_name');
        }]);

        return [
            'message' => $count . ' ' . $message,
            'tracked_count' => $market->items->count(),
            'items' => $market->items->map(function (SeededMarketItem $item) {
                return $this->itemPayload($item);
            })->values(),
        ];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
