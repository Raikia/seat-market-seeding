<?php

namespace Raikia\SeatMarketSeeding\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Raikia\SeatMarketSeeding\Models\MarketSeedingItemSource;
use Raikia\SeatMarketSeeding\Models\MarketSeedingTargetHistory;
use Raikia\SeatMarketSeeding\Models\MarketStockDailySummary;
use Raikia\SeatMarketSeeding\Models\MarketStockHistory;
use Raikia\SeatMarketSeeding\Models\SeededMarket;
use Raikia\SeatMarketSeeding\Models\SeededMarketItem;
use Raikia\SeatMarketSeeding\Services\MarketSeedingSettings;
use Raikia\SeatMarketSeeding\Services\MarketStockReport;
use Raikia\SeatMarketSeeding\Services\SavedFittingSource;
use Raikia\SeatMarketSeeding\Services\StockTargetQuantity;
use Raikia\SeatMarketSeeding\Services\StockTargetProjector;
use Raikia\SeatMarketSeeding\Support\MarketSeedingCache;
use Seat\Eveapi\Models\Market\MarketOrder;
use Seat\Eveapi\Models\Market\Price;
use Seat\Eveapi\Models\Sde\InvCategory;
use Seat\Eveapi\Models\Sde\InvGroup;
use Seat\Eveapi\Models\Sde\InvType;
use Seat\Web\Http\Controllers\Controller;

class MarketSeedingController extends Controller
{
    const MIN_RECOMMENDATION_DAYS_WITH_DATA = 7;

    public function index(MarketStockReport $report)
    {
        $stockReport = Cache::remember($this->dashboardCacheKey(), now()->addMinutes(3), function () use ($report) {
            $markets = $this->visibleMarkets()
                ->with('items.sources', 'role')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            return $report->build($markets);
        });

        return view('seat-market-seeding::index', compact('stockReport'));
    }

    public function itemHistory(Request $request, SeededMarketItem $item, MarketStockReport $report)
    {
        $market = $item->market;

        abort_unless($market && $this->canViewMarket($market), 403);

        $days = $this->historyDays($request);
        $events = MarketStockHistory::query()
            ->where('item_id', $item->id)
            ->latest()
            ->limit(25)
            ->get()
            ->map(function (MarketStockHistory $event) {
                return [
                    'created_at' => optional($event->created_at)->format('Y-m-d H:i'),
                    'created_at_order' => optional($event->created_at)->timestamp,
                    'previous_status' => $event->previous_status,
                    'current_status' => $event->current_status,
                    'current_quantity' => (int) $event->current_quantity,
                    'warning_quantity' => (int) $event->warning_quantity,
                    'desired_quantity' => (int) $event->desired_quantity,
                ];
            });
        $targetHistory = MarketSeedingTargetHistory::query()
            ->where(function ($query) use ($item) {
                $query->where('item_id', $item->id)
                    ->orWhere(function ($query) use ($item) {
                        $query->where('market_id', $item->market_id)
                            ->where('type_id', $item->type_id);
                    });
            })
            ->latest()
            ->limit(25)
            ->get()
            ->map(function (MarketSeedingTargetHistory $history) {
                return [
                    'created_at' => optional($history->created_at)->format('Y-m-d H:i'),
                    'created_at_order' => optional($history->created_at)->timestamp,
                    'change_type' => $history->change_type,
                    'change_type_label' => $history->changeTypeLabel(),
                    'old_target_quantity' => $history->old_target_quantity,
                    'new_target_quantity' => $history->new_target_quantity,
                    'old_warning_quantity' => $history->old_warning_quantity,
                    'new_warning_quantity' => $history->new_warning_quantity,
                    'user_name' => $history->user_name ?: 'System',
                ];
            });

        return response()->json([
            'item' => [
                'id' => $item->id,
                'type_id' => $item->type_id,
                'type_name' => $item->type_name,
                'market_name' => $market->name,
                'location_name' => $market->location_name,
            ],
            'details' => $report->itemDetails($item),
            'source_details' => $this->itemSourceDetails($item),
            'trend' => $this->itemSalesTrend($item, $days),
            'events' => $events,
            'target_history' => $targetHistory,
        ]);
    }

    public function listingHelperPrices(Request $request, SeededMarket $market)
    {
        abort_unless($this->canViewMarket($market), 403);

        $data = $request->validate([
            'items' => 'required|array|max:500',
            'items.*' => 'required|string|max:255',
        ]);

        $names = collect($data['items'])
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique(fn ($name) => mb_strtolower($name))
            ->values();

        if ($names->isEmpty()) {
            return response()->json(['prices' => []]);
        }

        $types = InvType::query()
            ->whereIn('typeName', $names->all())
            ->get(['typeID', 'typeName']);
        $typesByName = $types->keyBy(fn (InvType $type) => mb_strtolower($type->typeName));
        $typeIds = $types->pluck('typeID')->map(fn ($typeId) => (int) $typeId)->values();
        $localPrices = $this->listingHelperSellPrices($market->location_id, $typeIds);
        $jitaPrices = $this->listingHelperSellPrices(MarketStockReport::JITA_STATION_ID, $typeIds);
        $fallbackPrices = Price::query()
            ->whereIn('type_id', $typeIds)
            ->get()
            ->mapWithKeys(function (Price $price) {
                return [
                    (int) $price->type_id => (float) ($price->sell_price ?: $price->average_price ?: 0),
                ];
            });

        $prices = $names->mapWithKeys(function ($name) use ($typesByName, $localPrices, $jitaPrices, $fallbackPrices) {
            $type = $typesByName->get(mb_strtolower($name));

            if (!$type) {
                return [$name => [
                    'found' => false,
                    'type_id' => null,
                    'type_name' => $name,
                    'local_price' => null,
                    'jita_price' => null,
                ]];
            }

            $typeId = (int) $type->typeID;

            return [$name => [
                'found' => true,
                'type_id' => $typeId,
                'type_name' => $type->typeName,
                'local_price' => $localPrices->get($typeId),
                'jita_price' => $jitaPrices->get($typeId) ?: $fallbackPrices->get($typeId),
            ]];
        });

        return response()->json(['prices' => $prices]);
    }

    private function itemSourceDetails(SeededMarketItem $item): array
    {
        $sources = $item->sources()
            ->with('trackedDoctrine.fitSettings')
            ->get();
        $manualSources = $sources->whereIn('source_type', [
            MarketSeedingItemSource::SOURCE_MANUAL,
            MarketSeedingItemSource::SOURCE_MANUAL_ADJUSTMENT,
        ]);
        $doctrineSources = $sources->where('source_type', MarketSeedingItemSource::SOURCE_DOCTRINE);
        $doctrines = [];

        foreach ($doctrineSources as $source) {
            $trackedDoctrine = $source->trackedDoctrine;

            if (!$trackedDoctrine) {
                continue;
            }

            $doctrines[] = [
                'id' => $trackedDoctrine->id,
                'doctrine_id' => $trackedDoctrine->doctrine_id,
                'name' => $trackedDoctrine->doctrine_name,
                'quantity' => (int) $source->quantity,
                'warning_quantity' => (int) $source->warning_quantity,
                'merge_mode' => $trackedDoctrine->merge_mode,
                'fit_aggregation_mode' => $trackedDoctrine->fit_aggregation_mode,
                'fits' => $this->doctrineFitContributions($trackedDoctrine, $item),
            ];
        }

        return [
            'flags' => [
                'manual' => $manualSources->isNotEmpty(),
                'doctrine' => $doctrineSources->isNotEmpty(),
            ],
            'manual' => $manualSources->map(function (MarketSeedingItemSource $source) {
                return [
                    'source_type' => $source->source_type,
                    'label' => $source->source_type === MarketSeedingItemSource::SOURCE_MANUAL_ADJUSTMENT
                        ? 'Manual adjustment'
                        : 'Manual add',
                    'quantity' => (int) $source->quantity,
                    'warning_quantity' => (int) $source->warning_quantity,
                ];
            })->values(),
            'doctrines' => collect($doctrines)->values(),
        ];
    }

    private function doctrineFitContributions($trackedDoctrine, SeededMarketItem $item)
    {
        try {
            $fits = app(SavedFittingSource::class)->doctrineFits((int) $trackedDoctrine->doctrine_id);
        } catch (\Throwable $e) {
            return [];
        }

        $fitSettings = $trackedDoctrine->fitSettings
            ->keyBy('fitting_id');

        return $fits
            ->map(function (array $fit) use ($fitSettings, $item) {
                $setting = $fitSettings->get((int) $fit['fitting_id']);
                $shipMultiplier = $setting ? (int) $setting->ship_multiplier : 0;
                $fittingMultiplier = $setting ? (int) $setting->fitting_multiplier : 0;
                $contributions = [];

                if ((int) ($fit['ship_type_id'] ?? 0) === (int) $item->type_id && $shipMultiplier > 0) {
                    $contributions[] = [
                        'kind' => 'Ship hull',
                        'quantity' => $shipMultiplier,
                    ];
                }

                foreach (($fit['items'] ?? []) as $fitItem) {
                    if ((int) ($fitItem['type_id'] ?? 0) !== (int) $item->type_id || $fittingMultiplier < 1) {
                        continue;
                    }

                    $contributions[] = [
                        'kind' => $fitItem['slot_group'] ?: 'Fitting item',
                        'quantity' => (int) $fitItem['quantity'] * $fittingMultiplier,
                    ];
                }

                if (empty($contributions)) {
                    return null;
                }

                return [
                    'fitting_id' => (int) $fit['fitting_id'],
                    'fitting_name' => $fit['fitting_name'],
                    'ship_type_id' => (int) ($fit['ship_type_id'] ?? 0),
                    'ship_type_name' => $fit['ship_type_name'] ?: 'Unknown Ship',
                    'ship_multiplier' => $shipMultiplier,
                    'fitting_multiplier' => $fittingMultiplier,
                    'contributions' => $contributions,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function history(Request $request, MarketSeedingSettings $settings, MarketStockReport $report)
    {
        $days = $this->historyDays($request);
        $historyCoverageDays = $this->historyCoverageDays($request, $days);
        $markets = $this->visibleMarkets()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $chartData = $this->historyChartData($request, $historyCoverageDays);
        $salesChartData = $this->salesChartData($request, $historyCoverageDays);
        $salesSummary = $this->salesSummary($request, $historyCoverageDays, $historyCoverageDays);
        $globalMetrics = $this->globalHistoryMetrics($request, $historyCoverageDays, $historyCoverageDays);
        $topSoldItems = $this->topSoldItems($request, $historyCoverageDays);
        $categorySales = $this->categorySales($request, $historyCoverageDays);
        $restockLeaders = $this->restockLeaders($request, $historyCoverageDays);
        $typeCategories = $this->historyTypeCategories($request);
        $recommendationSalesDays = $settings->recommendationSalesDays();
        $recommendationBufferPercentage = $settings->recommendationBufferPercentage();
        $recommendationCoverageDays = $this->historyCoverageDays($request, $recommendationSalesDays);
        $recommendationMetrics = $this->recommendationMetrics($request, $recommendationSalesDays);
        $attentionItems = $this->recommendationRows($request, $recommendationSalesDays, $recommendationCoverageDays, $recommendationMetrics, $recommendationSalesDays, $recommendationBufferPercentage);
        $this->attachRecommendationEconomics($attentionItems, $report);
        $heatmapData = $this->marketCategoryHeatmap($request, $historyCoverageDays);
        $historyAjaxUrl = route('market-seeding.history.transitions', $request->only('market_id', 'status', 'type_category', 'days'));

        $this->attachTypeCategories($restockLeaders);
        $this->attachCurrentItemValues($topSoldItems);

        $this->attachRecommendations($topSoldItems, $recommendationCoverageDays, $recommendationMetrics, $recommendationSalesDays, $recommendationBufferPercentage);
        $this->attachRecommendations($restockLeaders, $recommendationCoverageDays, $recommendationMetrics, $recommendationSalesDays, $recommendationBufferPercentage);
        $this->attachLowEmptyEventCounts($restockLeaders);
        $this->attachRestockPace($restockLeaders, $historyCoverageDays);

        return view('seat-market-seeding::history', compact(
            'markets',
            'chartData',
            'salesChartData',
            'salesSummary',
            'globalMetrics',
            'topSoldItems',
            'categorySales',
            'restockLeaders',
            'attentionItems',
            'heatmapData',
            'typeCategories',
            'days',
            'historyCoverageDays',
            'recommendationSalesDays',
            'recommendationBufferPercentage',
            'historyAjaxUrl'
        ));
    }

    public function historyTransitions(Request $request, MarketSeedingSettings $settings)
    {
        $days = $this->historyDays($request);
        $historyCoverageDays = $this->historyCoverageDays($request, $days);
        $query = $this->filteredVisibleHistory($request, $historyCoverageDays);
        $recordsTotal = (clone $query)->count();
        $search = trim((string) $request->input('search.value', ''));

        if ($search !== '') {
            $escaped = $this->escapeLike($search);
            $query->where(function ($query) use ($escaped) {
                $query->where('market_name', 'like', '%' . $escaped . '%')
                    ->orWhere('location_name', 'like', '%' . $escaped . '%')
                    ->orWhere('type_name', 'like', '%' . $escaped . '%')
                    ->orWhere('current_status', 'like', '%' . $escaped . '%')
                    ->orWhere('previous_status', 'like', '%' . $escaped . '%');
            });
        }

        $recordsFiltered = (clone $query)->count();
        $this->applyHistoryDataTableOrder($query, $request);
        $length = (int) $request->input('length', 25);
        $start = max(0, (int) $request->input('start', 0));

        if ($length > 0) {
            $query->skip($start)->take(min($length, 100));
        }

        $events = $query->get();
        $this->attachTypeCategories($events);
        $this->attachCurrentItemValues($events);

        $recommendationSalesDays = $settings->recommendationSalesDays();
        $recommendationCoverageDays = $this->historyCoverageDays($request, $recommendationSalesDays);
        $recommendationMetrics = $this->recommendationMetrics($request, $recommendationSalesDays);
        $this->attachRecommendations(
            $events,
            $recommendationCoverageDays,
            $recommendationMetrics,
            $recommendationSalesDays,
            $settings->recommendationBufferPercentage()
        );

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $events->map(function (MarketStockHistory $event) {
                return $this->historyTransitionRow($event);
            })->values(),
        ]);
    }

    public function applyHistoryRecommendations(Request $request, MarketSeedingSettings $settings, StockTargetProjector $projector)
    {
        $recommendationSalesDays = $settings->recommendationSalesDays();
        $recommendationCoverageDays = $this->historyCoverageDays($request, $recommendationSalesDays);
        $recommendationMetrics = $this->recommendationMetrics($request, $recommendationSalesDays);
        $recommendations = $this->recommendationRows(
            $request,
            $recommendationSalesDays,
            $recommendationCoverageDays,
            $recommendationMetrics,
            $recommendationSalesDays,
            $settings->recommendationBufferPercentage()
        );
        $requestedItemIds = collect($request->input('item_ids', []))
            ->map(fn ($itemId) => (int) $itemId)
            ->filter()
            ->values();

        if ($requestedItemIds->isNotEmpty()) {
            $recommendations = $recommendations
                ->whereIn('item_id', $requestedItemIds->all())
                ->values();
        }

        $updated = 0;
        $errors = [];

        foreach ($recommendations as $recommendation) {
            $item = SeededMarketItem::query()->with('market')->find($recommendation->item_id);

            if (!$item || !$item->market || !$this->canViewMarket($item->market)) {
                $errors[] = $recommendation->type_name . ' is no longer available.';
                continue;
            }

            try {
                $projector->setEffectiveTarget(
                    $item,
                    (int) $recommendation->recommended_quantity,
                    $this->recommendedWarningQuantity($recommendation),
                    null,
                    MarketSeedingTargetHistory::CHANGE_RECOMMENDATION
                );
                $updated++;
            } catch (\Throwable $e) {
                $errors[] = $recommendation->type_name . ': ' . $e->getMessage();
            }
        }

        return response()->json([
            'message' => $updated . ' recommendation(s) applied.',
            'updated' => $updated,
            'errors' => $errors,
        ]);
    }

    private function filteredVisibleHistory(Request $request, ?int $days = null)
    {
        $days = $days ?: $this->historyDays($request);

        return $this->visibleHistory()
            ->when($request->filled('market_id'), function ($query) use ($request) {
                $query->where('market_id', $request->integer('market_id'));
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('current_status', $request->input('status'));
            })
            ->when($request->filled('type_category'), function ($query) use ($request) {
                $this->applyTypeCategoryFilter($query, $request->input('type_category'));
            })
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay());
    }

    private function filteredVisibleSummaries(Request $request, int $days)
    {
        return $this->visibleSummaries()
            ->when($request->filled('market_id'), function ($query) use ($request) {
                $query->where('market_id', $request->integer('market_id'));
            })
            ->when($request->filled('type_category'), function ($query) use ($request) {
                $query->where('type_category', $request->input('type_category'));
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                if ($request->input('status') === 'low') {
                    $query->where('low_events', '>', 0);
                } elseif ($request->input('status') === 'empty') {
                    $query->where('empty_events', '>', 0);
                } elseif ($request->input('status') === 'stocked') {
                    $query->where('stocked_events', '>', 0);
                }
            })
            ->where('summary_date', '>=', now()->subDays($days - 1)->toDateString());
    }

    private function historyCoverageDays(Request $request, int $days): int
    {
        $oldestSummaryDate = $this->filteredVisibleSummaries($request, $days)
            ->min('summary_date');

        return $this->coverageDaysFromSummaryDate($oldestSummaryDate, $days);
    }

    private function itemHistoryCoverageDays(SeededMarketItem $item, int $days): int
    {
        $oldestSummaryDate = MarketStockDailySummary::query()
            ->where('item_id', $item->id)
            ->where('summary_date', '>=', now()->subDays($days - 1)->toDateString())
            ->min('summary_date');

        return $this->coverageDaysFromSummaryDate($oldestSummaryDate, $days);
    }

    private function coverageDaysFromSummaryDate($oldestSummaryDate, int $days): int
    {
        if (!$oldestSummaryDate) {
            return max(1, $days);
        }

        $coveredDays = \Carbon\Carbon::parse($oldestSummaryDate)
            ->startOfDay()
            ->diffInDays(now()->startOfDay()) + 1;

        return max(1, min($days, (int) $coveredDays));
    }

    private function salesSummary(Request $request, int $days, int $historyCoverageDays): array
    {
        $summary = $this->filteredVisibleSummaries($request, $days)
            ->selectRaw('COALESCE(SUM(estimated_sold_quantity), 0) as estimated_sold')
            ->selectRaw('COALESCE(SUM(restocked_quantity), 0) as restocked')
            ->selectRaw('COALESCE(SUM(sales_events), 0) as sales_events')
            ->first();
        $trackedLines = $this->filteredVisibleSummaries($request, $days)
            ->select('market_id', 'type_id')
            ->distinct()
            ->get()
            ->count();

        $totalSold = (int) optional($summary)->estimated_sold;

        return [
            'estimated_sold' => $totalSold,
            'restocked' => (int) optional($summary)->restocked,
            'tracked_lines' => $trackedLines,
            'sales_events' => (int) optional($summary)->sales_events,
            'average_daily_sold' => $historyCoverageDays > 0 ? round($totalSold / $historyCoverageDays, 1) : 0,
        ];
    }

    private function globalHistoryMetrics(Request $request, int $days, int $historyCoverageDays): array
    {
        $rows = $this->filteredVisibleSummaries($request, $days)
            ->select('type_id')
            ->selectRaw('COALESCE(SUM(estimated_sold_quantity), 0) as estimated_sold')
            ->selectRaw('COALESCE(SUM(restocked_quantity), 0) as restocked')
            ->selectRaw('COALESCE(SUM(low_events + empty_events), 0) as restock_events')
            ->selectRaw('COALESCE(SUM(total_shortage), 0) as total_shortage')
            ->groupBy('type_id')
            ->get();

        $typeIds = $rows->pluck('type_id')->map(fn ($typeId) => (int) $typeId)->unique()->values();
        $prices = $this->historyPrices($typeIds);
        $soldValue = 0.0;
        $restockedValue = 0.0;
        $pricedTypes = 0;

        foreach ($rows as $row) {
            $price = (float) $prices->get((int) $row->type_id, 0);

            if ($price > 0) {
                $pricedTypes++;
            }

            $soldValue += (int) $row->estimated_sold * $price;
            $restockedValue += (int) $row->restocked * $price;
        }

        $summary = $this->filteredVisibleSummaries($request, $days)
            ->selectRaw('COALESCE(SUM(low_events + empty_events), 0) as restock_events')
            ->selectRaw('COALESCE(SUM(total_shortage), 0) as total_shortage')
            ->first();

        return [
            'sold_value' => $soldValue,
            'restocked_value' => $restockedValue,
            'net_value' => $soldValue - $restockedValue,
            'average_daily_sold_value' => $historyCoverageDays > 0 ? $soldValue / $historyCoverageDays : 0,
            'restock_events' => (int) optional($summary)->restock_events,
            'total_shortage' => (int) optional($summary)->total_shortage,
            'priced_types' => $pricedTypes,
            'total_types' => $typeIds->count(),
        ];
    }

    private function historyPrices($typeIds)
    {
        $typeIds = collect($typeIds)
            ->map(fn ($typeId) => (int) $typeId)
            ->filter()
            ->unique()
            ->values();

        if ($typeIds->isEmpty()) {
            return collect();
        }

        return Cache::remember(MarketSeedingCache::historyPriceKey($typeIds), now()->addMinutes(10), function () use ($typeIds) {
            return $this->loadHistoryPrices($typeIds);
        });
    }

    private function loadHistoryPrices($typeIds)
    {
        $jitaPrices = MarketOrder::query()
            ->selectRaw('type_id, MIN(price) as price')
            ->where('location_id', MarketStockReport::JITA_STATION_ID)
            ->whereIn('type_id', $typeIds)
            ->where('is_buy_order', false)
            ->groupBy('type_id')
            ->pluck('price', 'type_id')
            ->map(fn ($price) => (float) $price);
        $missingTypeIds = $typeIds->reject(fn ($typeId) => $jitaPrices->has($typeId))->values();

        if ($missingTypeIds->isEmpty()) {
            return $jitaPrices;
        }

        $fallbackPrices = Price::query()
            ->whereIn('type_id', $missingTypeIds)
            ->get()
            ->mapWithKeys(function (Price $price) {
                return [
                    (int) $price->type_id => (float) ($price->sell_price ?: $price->average_price),
                ];
            })
            ->filter(fn ($price) => $price > 0);

        return $jitaPrices->union($fallbackPrices);
    }

    private function salesChartData(Request $request, int $days): array
    {
        $labels = $this->historyDateLabels($days);
        $events = $this->filteredVisibleSummaries($request, $days)
            ->selectRaw('summary_date as event_date')
            ->selectRaw('SUM(estimated_sold_quantity) as estimated_sold')
            ->selectRaw('SUM(restocked_quantity) as restocked')
            ->groupBy('event_date')
            ->get()
            ->keyBy('event_date');

        $sold = [];
        $restocked = [];

        foreach ($labels as $label) {
            $row = $events->get($label);
            $sold[] = $row ? (int) $row->estimated_sold : 0;
            $restocked[] = $row ? (int) $row->restocked : 0;
        }

        return [
            'labels' => $labels->map(function (string $date) {
                return \Carbon\Carbon::createFromFormat('Y-m-d', $date)->format('M j');
            })->values(),
            'series' => [
                'estimated_sold' => $sold,
                'restocked' => $restocked,
            ],
        ];
    }

    private function topSoldItems(Request $request, int $days)
    {
        return $this->filteredVisibleSummaries($request, $days)
            ->select([
                'market_id',
                'item_id',
                'market_name',
                'location_name',
                'type_id',
                'type_name',
                'type_category',
            ])
            ->selectRaw('SUM(estimated_sold_quantity) as estimated_sold')
            ->selectRaw('SUM(restocked_quantity) as restocked')
            ->selectRaw('SUM(sales_events) as sales_events')
            ->selectRaw('MAX(last_sold_at) as last_sold_at')
            ->groupBy('market_id', 'item_id', 'market_name', 'location_name', 'type_id', 'type_name', 'type_category')
            ->havingRaw('SUM(estimated_sold_quantity) > 0')
            ->orderByDesc('estimated_sold')
            ->orderByDesc('sales_events')
            ->limit(20)
            ->get();
    }

    private function attachCurrentItemValues($rows): void
    {
        $itemIds = collect($rows)
            ->pluck('item_id')
            ->filter()
            ->unique()
            ->values();

        if ($itemIds->isEmpty()) {
            return;
        }

        $summaries = MarketStockDailySummary::query()
            ->whereIn('item_id', $itemIds)
            ->orderByDesc('summary_date')
            ->orderByDesc('updated_at')
            ->get()
            ->unique('item_id')
            ->keyBy('item_id');
        $items = SeededMarketItem::query()
            ->with('sources')
            ->whereIn('id', $itemIds)
            ->get()
            ->keyBy('id');

        foreach ($rows as $row) {
            $summary = $summaries->get($row->item_id);
            $item = $items->get($row->item_id);

            if ($summary) {
                $row->latest_seen_quantity = (int) $summary->latest_current_quantity;
            }

            if ($item) {
                $row->target_quantity = (int) $item->desired_quantity;
                $row->desired_quantity = (int) $item->desired_quantity;
                $row->warning_quantity = (int) $item->warning_quantity;
                $row->source_flags = $item->sourceFlags();
            } elseif ($summary) {
                $row->target_quantity = (int) $summary->latest_desired_quantity;
                $row->desired_quantity = (int) $summary->latest_desired_quantity;
                $row->warning_quantity = (int) $summary->latest_warning_quantity;
                $row->source_flags = ['manual' => false, 'doctrine' => false];
            }
        }
    }

    private function categorySales(Request $request, int $days)
    {
        return $this->filteredVisibleSummaries($request, $days)
            ->select('type_category')
            ->selectRaw('SUM(estimated_sold_quantity) as estimated_sold')
            ->groupBy('type_category')
            ->havingRaw('SUM(estimated_sold_quantity) > 0')
            ->orderByDesc('estimated_sold')
            ->limit(8)
            ->get();
    }

    private function recommendationRows(Request $request, int $days, int $historyCoverageDays, $metrics, int $recommendationSalesDays, int $recommendationBufferPercentage)
    {
        $rows = $this->filteredVisibleSummaries($request, $days)
            ->whereNotNull('item_id')
            ->select([
                'market_id',
                'item_id',
                'market_name',
                'location_name',
                'type_id',
                'type_name',
                'type_category',
            ])
            ->selectRaw('SUM(estimated_sold_quantity) as estimated_sold')
            ->selectRaw('SUM(restocked_quantity) as restocked')
            ->selectRaw('SUM(sales_events) as sales_events')
            ->groupBy('market_id', 'item_id', 'market_name', 'location_name', 'type_id', 'type_name', 'type_category')
            ->get();

        $restockMetrics = $this->filteredVisibleHistory($request, $historyCoverageDays)
            ->whereIn('current_status', ['low', 'empty'])
            ->select('item_id')
            ->selectRaw('COUNT(*) as restock_events')
            ->selectRaw('SUM(CASE WHEN CAST(desired_quantity AS SIGNED) > CAST(current_quantity AS SIGNED) THEN CAST(desired_quantity AS SIGNED) - CAST(current_quantity AS SIGNED) ELSE 0 END) as total_shortage')
            ->groupBy('item_id')
            ->get()
            ->keyBy('item_id');

        foreach ($rows as $row) {
            $restock = $restockMetrics->get($row->item_id);
            $row->restock_events = (int) optional($restock)->restock_events;
            $row->total_shortage = (int) optional($restock)->total_shortage;
        }

        $this->attachCurrentItemValues($rows);
        $this->attachRecommendations($rows, $historyCoverageDays, $metrics, $recommendationSalesDays, $recommendationBufferPercentage);
        $this->attachLowEmptyEventCounts($rows);

        return $rows
            ->filter(fn ($row) => $row->recommendation_differs)
            ->sortByDesc(fn ($row) => (int) $row->recommended_quantity - (int) $row->current_target_quantity)
            ->values();
    }

    private function sparklinePoints($values): string
    {
        $values = collect($values)->values();
        $count = max(1, $values->count() - 1);
        $max = max(1, (int) $values->max());

        return $values->map(function ($value, int $index) use ($count, $max) {
            $x = round(($index / $count) * 100, 2);
            $y = round(28 - (((int) $value / $max) * 24), 2);

            return $x . ',' . $y;
        })->implode(' ');
    }

    private function itemSalesTrend(SeededMarketItem $item, int $days): array
    {
        $coverageDays = $this->itemHistoryCoverageDays($item, $days);
        $labels = $this->historyDateLabels($coverageDays);
        $sales = MarketStockDailySummary::query()
            ->where('item_id', $item->id)
            ->where('summary_date', '>=', now()->subDays($coverageDays - 1)->toDateString())
            ->selectRaw('summary_date as sale_date')
            ->selectRaw('SUM(estimated_sold_quantity) as estimated_sold')
            ->groupBy('sale_date')
            ->get()
            ->keyBy('sale_date');
        $values = $labels->map(function (string $date) use ($sales) {
            return (int) optional($sales->get($date))->estimated_sold;
        })->values();

        return [
            'labels' => $labels->map(function (string $date) {
                return \Carbon\Carbon::createFromFormat('Y-m-d', $date)->format('M j');
            })->values(),
            'values' => $values,
            'points' => $this->sparklinePoints($values),
            'total' => (int) $values->sum(),
            'days' => $coverageDays,
            'selected_days' => $days,
        ];
    }

    private function attachLowEmptyEventCounts($rows): void
    {
        foreach ($rows as $row) {
            $row->low_empty_events = (int) ($row->restock_events ?? 0);
        }
    }

    private function attachRestockPace($rows, int $historyCoverageDays): void
    {
        foreach ($rows as $row) {
            $events = (int) ($row->restock_events ?? 0);
            $row->average_days_between_restock_needs = $events > 0 ? round($historyCoverageDays / $events, 1) : null;
        }
    }

    private function attachRecommendationEconomics($rows, MarketStockReport $report): void
    {
        $items = SeededMarketItem::query()
            ->with('market')
            ->whereIn('id', collect($rows)->pluck('item_id')->filter()->unique()->values())
            ->get()
            ->keyBy('id');
        $detailsByItem = $report->itemDetailsForItems($items->values());

        foreach ($rows as $row) {
            $details = $detailsByItem->get($row->item_id, []);
            $deltaQuantity = max(0, (int) $row->recommended_quantity - (int) $row->current_target_quantity);
            $jitaPrice = (float) ($details['jita_price'] ?? 0);
            $itemVolume = (float) ($details['item_volume'] ?? 0);

            $row->recommendation_delta_quantity = $deltaQuantity;
            $row->recommendation_delta_cost = $deltaQuantity * $jitaPrice;
            $row->recommendation_delta_volume = $deltaQuantity * $itemVolume;
        }
    }

    private function recommendedWarningQuantity($recommendation): int
    {
        $currentTarget = max(1, (int) $recommendation->current_target_quantity);
        $currentWarning = max(0, (int) $recommendation->warning_quantity);

        if ($currentWarning === 0) {
            return 0;
        }

        return app(StockTargetQuantity::class)->scaleWarningQuantity(
            max(1, (int) $recommendation->recommended_quantity),
            $currentTarget,
            $currentWarning
        );
    }

    private function marketCategoryHeatmap(Request $request, int $days): array
    {
        $rows = $this->filteredVisibleSummaries($request, $days)
            ->select('market_id', 'market_name', 'type_category')
            ->selectRaw('SUM(estimated_sold_quantity) as estimated_sold')
            ->selectRaw('SUM(restocked_quantity) as restocked')
            ->groupBy('market_id', 'market_name', 'type_category')
            ->havingRaw('(SUM(estimated_sold_quantity) + SUM(restocked_quantity)) > 0')
            ->orderBy('market_name')
            ->orderBy('type_category')
            ->get();

        $categories = $rows->pluck('type_category')->filter()->unique()->sort()->values();
        $maxMovement = max(1, (int) $rows->map(fn ($row) => (int) $row->estimated_sold + (int) $row->restocked)->max());
        $markets = $rows
            ->groupBy('market_id')
            ->map(function ($marketRows) use ($categories, $maxMovement) {
                $byCategory = $marketRows->keyBy('type_category');

                return [
                    'name' => optional($marketRows->first())->market_name,
                    'categories' => $categories->mapWithKeys(function ($category) use ($byCategory, $maxMovement) {
                        $row = $byCategory->get($category);
                        $sold = (int) optional($row)->estimated_sold;
                        $restocked = (int) optional($row)->restocked;
                        $movement = $sold + $restocked;

                        return [$category => [
                            'sold' => $sold,
                            'restocked' => $restocked,
                            'movement' => $movement,
                            'intensity' => round($movement / $maxMovement, 3),
                        ]];
                    }),
                ];
            })
            ->values();

        return [
            'categories' => $categories,
            'markets' => $markets,
        ];
    }

    private function recommendationMetrics(Request $request, int $days)
    {
        return $this->filteredVisibleSummaries($request, $days)
            ->select('item_id')
            ->selectRaw('SUM(estimated_sold_quantity) as estimated_sold')
            ->selectRaw('SUM(restocked_quantity) as restocked')
            ->selectRaw('SUM(sales_events) as sales_events')
            ->selectRaw('MIN(summary_date) as oldest_summary_date')
            ->groupBy('item_id')
            ->get()
            ->each(function ($metric) use ($days) {
                $metric->coverage_days = $this->coverageDaysFromSummaryDate($metric->oldest_summary_date, $days);
            })
            ->keyBy('item_id');
    }

    private function attachRecommendations($rows, int $fallbackDays, $metrics, int $recommendationSalesDays, int $recommendationBufferPercentage): void
    {
        foreach ($rows as $row) {
            $target = max(1, (int) ($row->target_quantity ?? $row->desired_quantity ?? 1));
            $metric = $metrics->get($row->item_id);
            $estimatedSold = (int) (optional($metric)->estimated_sold ?? $row->estimated_sold ?? 0);
            $days = max(1, (int) (optional($metric)->coverage_days ?? $fallbackDays));
            $dailySold = $days > 0 ? $estimatedSold / $days : 0;
            $bufferMultiplier = 1 + ($recommendationBufferPercentage / 100);
            $salesRecommendation = (int) ceil($dailySold * $recommendationSalesDays * $bufferMultiplier);
            $recommended = max(1, $target, $salesRecommendation);
            $row->current_target_quantity = $target;
            $row->recommended_quantity = $recommended;
            $row->recommendation_differs = $recommended !== $target;
            $row->recommendation_driver = null;
            $row->recommendation_driver_label = null;
            $row->recommendation_sales_days_with_data = $days;
            $row->recommendation_estimated_sold = $estimatedSold;
            $row->recommendation_daily_sold = round($dailySold, 2);
            $row->recommendation_sales_window = $recommendationSalesDays;
            $row->recommendation_buffer_multiplier = $bufferMultiplier;
            $row->recommendation_sales_target = max(1, $salesRecommendation);
            $row->recommendation_raw_sales_target = $salesRecommendation;
            $row->recommendation_existing_target_covers = $target >= max(1, $salesRecommendation);

            if ($days < self::MIN_RECOMMENDATION_DAYS_WITH_DATA) {
                $row->recommended_quantity = $target;
                $row->recommendation_differs = false;
                $row->recommendation_driver = 'insufficient_data';
                $row->recommendation_driver_label = 'Needs more data';
                $row->recommendation_reason = sprintf(
                    "No recommendation is shown yet because this item only has %s day%s of sales history.\n\nAt least %s days with data are required before target recommendations are made.\n\nCurrent sales signal: %s estimated sold, which is about %s per day.",
                    number_format($days),
                    $days === 1 ? '' : 's',
                    number_format(self::MIN_RECOMMENDATION_DAYS_WITH_DATA),
                    number_format($estimatedSold),
                    number_format($dailySold, 2)
                );

                continue;
            }

            if ($recommended <= $target) {
                $row->recommendation_driver = 'covered';
                $row->recommendation_driver_label = 'Covered';
                $row->recommendation_reason = sprintf(
                    "Current target: %s\n\nSales signal: In the last %s days with data, this item had %s estimated sold, which is about %s per day.\n\nFormula: %s sold / %s days * %s sales days * %s buffer = %s.\n\nResult: The sales-based target does not exceed the current target, so no increase is recommended.\n\nNote: Low or empty stock events are shown elsewhere for context, but they are not used to calculate target recommendations.",
                    number_format($target),
                    number_format($days),
                    number_format($estimatedSold),
                    number_format($dailySold, 2),
                    number_format($estimatedSold),
                    number_format($days),
                    number_format($recommendationSalesDays),
                    number_format($bufferMultiplier, 2) . 'x',
                    number_format($salesRecommendation)
                );
            } else {
                $row->recommendation_driver = 'sales';
                $row->recommendation_driver_label = 'Sales-driven';
                $row->recommendation_reason = sprintf(
                    "Recommended because recent estimated sales suggest the current target may not cover enough days of demand.\n\nCurrent target: %s\n\nSales signal: In the last %s days with data, this item had %s estimated sold, which is about %s per day.\n\nFormula: %s sold / %s days * %s sales days * %s buffer = %s.\n\nResult: Recent sales pace is driving this recommendation.\n\nNote: Low or empty stock events are shown elsewhere for context, but they are not used to calculate target recommendations.",
                    number_format($target),
                    number_format($days),
                    number_format($estimatedSold),
                    number_format($dailySold, 2),
                    number_format($estimatedSold),
                    number_format($days),
                    number_format($recommendationSalesDays),
                    number_format($bufferMultiplier, 2) . 'x',
                    number_format($salesRecommendation)
                );
            }
        }
    }

    private function historyDays(Request $request): int
    {
        $days = (int) $request->input('days', 90);

        return in_array($days, [7, 30, 60, 90, 180, 365], true) ? $days : 90;
    }

    private function historyDateLabels(int $days)
    {
        return collect(range($days - 1, 0))
            ->map(function (int $daysAgo) {
                return now()->subDays($daysAgo)->format('Y-m-d');
            });
    }

    private function historyChartData(Request $request, int $days): array
    {
        $labels = $this->historyDateLabels($days);

        $events = $this->filteredVisibleSummaries($request, $days)
            ->select('summary_date')
            ->selectRaw('SUM(low_events) as low_events')
            ->selectRaw('SUM(empty_events) as empty_events')
            ->selectRaw('SUM(stocked_events) as stocked_events')
            ->groupBy('summary_date')
            ->get()
            ->keyBy(function (MarketStockDailySummary $event) {
                return optional($event->summary_date)->format('Y-m-d');
            });

        $series = [
            'low' => [],
            'empty' => [],
            'stocked' => [],
        ];

        foreach ($labels as $label) {
            $dayEvents = $events->get($label);
            $series['low'][] = $dayEvents ? (int) $dayEvents->low_events : 0;
            $series['empty'][] = $dayEvents ? (int) $dayEvents->empty_events : 0;
            $series['stocked'][] = $dayEvents ? (int) $dayEvents->stocked_events : 0;
        }

        return [
            'labels' => $labels->map(function (string $date) {
                return \Carbon\Carbon::createFromFormat('Y-m-d', $date)->format('M j');
            })->values(),
            'series' => $series,
        ];
    }

    private function restockLeaders(Request $request, ?int $days = null)
    {
        return $this->filteredVisibleSummaries($request, $days ?: $this->historyDays($request))
            ->when($request->filled('market_id'), function ($query) use ($request) {
                $query->where('market_id', $request->integer('market_id'));
            })
            ->select([
                'market_id',
                'item_id',
                'market_name',
                'location_name',
                'type_id',
                'type_name',
                'type_category',
            ])
            ->selectRaw('SUM(low_events + empty_events) as restock_events')
            ->selectRaw('SUM(empty_events) as empty_events')
            ->selectRaw('SUM(low_events) as low_events')
            ->selectRaw('SUM(total_shortage) as total_shortage')
            ->selectRaw('MAX(last_needed_at) as last_needed_at')
            ->groupBy('market_id', 'item_id', 'market_name', 'location_name', 'type_id', 'type_name', 'type_category')
            ->havingRaw('SUM(low_events + empty_events) > 0')
            ->orderByDesc('restock_events')
            ->orderByDesc('empty_events')
            ->orderByDesc('total_shortage')
            ->get()
            ->tap(function ($rows) {
                $this->attachCurrentItemValues($rows);
            });
    }

    private function applyHistoryDataTableOrder($query, Request $request): void
    {
        $columns = [
            0 => 'created_at',
            1 => 'market_name',
            2 => 'type_name',
            3 => 'current_status',
            4 => 'current_quantity',
            5 => 'warning_quantity',
            6 => 'desired_quantity',
        ];
        $column = $columns[(int) $request->input('order.0.column', 0)] ?? 'created_at';
        $direction = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($column, $direction);

        if ($column !== 'created_at') {
            $query->latest();
        }
    }

    private function historyTransitionRow(MarketStockHistory $event): array
    {
        $itemHtml = view('seat-market-seeding::partials.source-icons', [
            'sourceFlags' => $event->source_flags ?? ['manual' => false, 'doctrine' => false],
            ])->render()
            . e($event->type_name)
            . '<span class="text-muted small market-seeding-item-type">' . e($event->type_category ?: 'Unknown') . '</span>';

        if ($event->recommendation_differs) {
            $itemHtml .= '<div class="history-recommendation-pill">Target '
                . number_format((int) $event->current_target_quantity)
                . ' &rarr; Recommended '
                . number_format((int) $event->recommended_quantity)
                . ' <i class="fas fa-question-circle history-recommendation-reason"'
                . ' data-recommendation-reason="' . e($event->recommendation_reason) . '"'
                . ' aria-label="' . e($event->recommendation_reason) . '"></i>'
                . '</div>';
        }

        $row = [
            optional($event->created_at)->format('Y-m-d H:i') ?: '-',
            e($event->market_name) . '<div class="text-muted small">' . e($event->location_name) . '</div>',
            $itemHtml,
            $this->historyStatusHtml($event),
            '<span class="float-right">' . number_format((int) $event->current_quantity) . '</span>',
            '<span class="float-right">' . number_format((int) $event->warning_quantity) . '</span>',
            '<span class="float-right">' . number_format((int) $event->desired_quantity) . '</span>',
        ];

        $canManage = auth()->user()->can('seat-market-seeding.manager');
        $row[] = $event->item_id
            ? '<span class="float-right"><button type="button" class="btn btn-link btn-xs p-0 history-item-action market-seeding-edit-target" title="' . ($canManage ? 'Edit target stock' : 'View item details') . '"'
                . ($canManage ? ' data-update-url="' . e(route('market-seeding.items.update', $event->item_id)) . '"' : '')
                . ' data-item-name="' . e($event->type_name) . '"'
                . ' data-market-name="' . e($event->market_name) . '"'
                . ' data-history-url="' . e(route('market-seeding.items.history', ['item' => $event->item_id, 'days' => request('days', 90)])) . '"'
                . ' data-desired-quantity="' . (int) $event->desired_quantity . '"'
                . ' data-warning-quantity="' . (int) $event->warning_quantity . '"'
                . $this->recommendationDataAttributes($event)
                . '>'
                . '<i class="fas ' . ($canManage ? 'fa-edit' : 'fa-eye') . '"></i></button></span>'
            : '<span class="float-right">-</span>';

        return $row;
    }

    private function recommendationDataAttributes($row): string
    {
        $attributes = [
            'data-recommended-quantity' => (int) ($row->recommended_quantity ?? 0),
            'data-recommendation-reason' => (string) ($row->recommendation_reason ?? ''),
            'data-recommendation-estimated-sold' => (int) ($row->recommendation_estimated_sold ?? 0),
            'data-recommendation-days-with-data' => (int) ($row->recommendation_sales_days_with_data ?? 0),
            'data-recommendation-daily-sold' => (float) ($row->recommendation_daily_sold ?? 0),
            'data-recommendation-sales-window' => (int) ($row->recommendation_sales_window ?? 0),
            'data-recommendation-buffer-multiplier' => (float) ($row->recommendation_buffer_multiplier ?? 1),
            'data-recommendation-sales-target' => (int) ($row->recommendation_sales_target ?? $row->recommended_quantity ?? 0),
            'data-recommendation-existing-target-covers' => !empty($row->recommendation_existing_target_covers) ? 1 : 0,
        ];

        return collect($attributes)
            ->map(fn ($value, string $attribute) => ' ' . $attribute . '="' . e($value) . '"')
            ->implode('');
    }

    private function historyStatusHtml(MarketStockHistory $event): string
    {
        $badge = [
            'stocked' => 'badge-success',
            'low' => 'badge-warning',
            'empty' => 'badge-danger',
        ][$event->current_status] ?? 'badge-secondary';

        return '<span class="badge ' . $badge . '">' . e(ucfirst($event->current_status)) . '</span>'
            . ($event->previous_status
                ? ' <span class="text-muted small">' . e($event->previous_status) . ' &rarr; ' . e($event->current_status) . '</span>'
                : '');
    }

    private function historyTypeCategories(Request $request)
    {
        $snapshotCategories = $this->visibleSummaries()
            ->when($request->filled('market_id'), function ($query) use ($request) {
                $query->where('market_id', $request->integer('market_id'));
            })
            ->distinct()
            ->pluck('type_category');

        $historyTable = (new MarketStockHistory)->getTable();
        $typeTable = (new InvType)->getTable();
        $groupTable = (new InvGroup)->getTable();
        $categoryTable = (new InvCategory)->getTable();

        $transitionCategories = $this->visibleHistory()
            ->when($request->filled('market_id'), function ($query) use ($request, $historyTable) {
                $query->where($historyTable . '.market_id', $request->integer('market_id'));
            })
            ->when($request->filled('status'), function ($query) use ($request, $historyTable) {
                $query->where($historyTable . '.current_status', $request->input('status'));
            })
            ->join($typeTable, $historyTable . '.type_id', '=', $typeTable . '.typeID')
            ->join($groupTable, $typeTable . '.groupID', '=', $groupTable . '.groupID')
            ->join($categoryTable, $groupTable . '.categoryID', '=', $categoryTable . '.categoryID')
            ->distinct()
            ->orderBy($categoryTable . '.categoryName')
            ->pluck($categoryTable . '.categoryName')
            ->map(function (string $categoryName) {
                return SeededMarketItem::CATEGORY_LABELS[$categoryName] ?? $categoryName;
            });

        return $snapshotCategories
            ->merge($transitionCategories)
            ->filter()
            ->unique()
            ->sort()
            ->values();
    }

    private function applyTypeCategoryFilter($query, string $typeCategory): void
    {
        $categoryName = array_flip(SeededMarketItem::CATEGORY_LABELS)[$typeCategory] ?? $typeCategory;
        $typeTable = (new InvType)->getTable();
        $groupTable = (new InvGroup)->getTable();
        $categoryTable = (new InvCategory)->getTable();

        $query->whereIn('type_id', function ($subQuery) use ($categoryName, $typeTable, $groupTable, $categoryTable) {
            $subQuery
                ->select($typeTable . '.typeID')
                ->from($typeTable)
                ->join($groupTable, $typeTable . '.groupID', '=', $groupTable . '.groupID')
                ->join($categoryTable, $groupTable . '.categoryID', '=', $categoryTable . '.categoryID')
                ->where($categoryTable . '.categoryName', $categoryName);
        });
    }

    private function attachTypeCategories($rows): void
    {
        $typeIds = collect($rows)
            ->pluck('type_id')
            ->filter()
            ->unique()
            ->values();

        if ($typeIds->isEmpty()) {
            return;
        }

        $categories = $this->typeCategoriesByTypeId($typeIds);

        foreach ($rows as $row) {
            $row->type_category = $categories->get($row->type_id, 'Unknown');
        }
    }

    private function typeCategoriesByTypeId($typeIds)
    {
        $typeTable = (new InvType)->getTable();
        $groupTable = (new InvGroup)->getTable();
        $categoryTable = (new InvCategory)->getTable();

        return InvType::query()
            ->join($groupTable, $typeTable . '.groupID', '=', $groupTable . '.groupID')
            ->join($categoryTable, $groupTable . '.categoryID', '=', $categoryTable . '.categoryID')
            ->whereIn($typeTable . '.typeID', $typeIds)
            ->selectRaw($typeTable . '.typeID as type_id, ' . $categoryTable . '.categoryName as category_name')
            ->get()
            ->mapWithKeys(function ($row) {
                return [
                    (int) $row->type_id => SeededMarketItem::CATEGORY_LABELS[$row->category_name] ?? $row->category_name,
                ];
            });
    }

    private function visibleHistory()
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return MarketStockHistory::query();
        }

        $roleIds = $user->roles->pluck('id');

        return MarketStockHistory::query()
            ->whereHas('market', function ($query) use ($roleIds) {
                $query->whereNull('role_id')
                    ->orWhereIn('role_id', $roleIds);
            });
    }

    private function visibleSummaries()
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return MarketStockDailySummary::query();
        }

        $roleIds = $user->roles->pluck('id');

        return MarketStockDailySummary::query()
            ->whereHas('market', function ($query) use ($roleIds) {
                $query->whereNull('role_id')
                    ->orWhereIn('role_id', $roleIds);
            });
    }

    private function listingHelperSellPrices(int $locationId, $typeIds)
    {
        $typeIds = collect($typeIds)
            ->map(fn ($typeId) => (int) $typeId)
            ->filter()
            ->unique()
            ->values();

        if ($typeIds->isEmpty()) {
            return collect();
        }

        return MarketOrder::query()
            ->selectRaw('type_id, MIN(price) as price')
            ->where('location_id', $locationId)
            ->whereIn('type_id', $typeIds)
            ->where('is_buy_order', false)
            ->groupBy('type_id')
            ->pluck('price', 'type_id')
            ->map(fn ($price) => (float) $price);
    }

    private function visibleMarkets()
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return SeededMarket::query();
        }

        $roleIds = $user->roles->pluck('id');

        return SeededMarket::query()
            ->where(function ($query) use ($roleIds) {
                $query->whereNull('role_id')
                    ->orWhereIn('role_id', $roleIds);
            });
    }

    private function dashboardCacheKey(): string
    {
        $user = auth()->user();
        $roleIds = $user->roles->pluck('id')->sort()->values();
        $marketSnapshot = $this->visibleMarkets()
            ->select('id', 'updated_at', 'last_refreshed_at')
            ->orderBy('id')
            ->get();
        $marketIds = $marketSnapshot->pluck('id');
        $latestItemUpdate = $marketIds->isEmpty()
            ? null
            : SeededMarketItem::whereIn('market_id', $marketIds)->max('updated_at');
        $latestSourceUpdate = $marketIds->isEmpty()
            ? null
            : MarketSeedingItemSource::whereIn('market_id', $marketIds)->max('updated_at');
        $settings = app(MarketSeedingSettings::class);
        $itemCount = $marketIds->isEmpty()
            ? 0
            : SeededMarketItem::whereIn('market_id', $marketIds)->count();
        $sourceCount = $marketIds->isEmpty()
            ? 0
            : MarketSeedingItemSource::whereIn('market_id', $marketIds)->count();

        return 'seat-market-seeding:dashboard:' . md5(json_encode([
            'version' => 4,
            'user_id' => $user->id,
            'is_admin' => $user->isAdmin(),
            'roles' => $roleIds,
            'recommendation_sales_days' => $settings->recommendationSalesDays(),
            'markets' => $marketSnapshot->map(function (SeededMarket $market) {
                return [
                    'id' => $market->id,
                    'updated_at' => optional($market->updated_at)->timestamp,
                    'last_refreshed_at' => optional($market->last_refreshed_at)->timestamp,
                ];
            })->values(),
            'item_count' => $itemCount,
            'source_count' => $sourceCount,
            'latest_item_update' => optional($latestItemUpdate ? \Carbon\Carbon::parse($latestItemUpdate) : null)->timestamp,
            'latest_source_update' => optional($latestSourceUpdate ? \Carbon\Carbon::parse($latestSourceUpdate) : null)->timestamp,
        ]));
    }

    private function canViewMarket(SeededMarket $market): bool
    {
        $user = auth()->user();

        if ($user->isAdmin() || !$market->role_id) {
            return true;
        }

        return $user->roles->pluck('id')->contains($market->role_id);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
