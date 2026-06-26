<?php

namespace Raikia\SeatMarketSeeding\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Raikia\SeatMarketSeeding\Models\MarketSeedingItemSource;
use Raikia\SeatMarketSeeding\Models\MarketStockHistory;
use Raikia\SeatMarketSeeding\Models\MarketStockSnapshot;
use Raikia\SeatMarketSeeding\Models\SeededMarket;
use Raikia\SeatMarketSeeding\Models\SeededMarketItem;
use Raikia\SeatMarketSeeding\Services\MarketSeedingSettings;
use Raikia\SeatMarketSeeding\Services\MarketStockReport;
use Raikia\SeatMarketSeeding\Services\StockTargetProjector;
use Seat\Eveapi\Models\Sde\InvCategory;
use Seat\Eveapi\Models\Sde\InvGroup;
use Seat\Eveapi\Models\Sde\InvType;
use Seat\Web\Http\Controllers\Controller;

class MarketSeedingController extends Controller
{
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

    public function export(SeededMarket $market, MarketStockReport $report)
    {
        abort_unless($this->canViewMarket($market), 403);

        $stockReport = $report->build(collect([$market]));
        $export = $stockReport['markets'][0]['export'] ?? '';

        return response($export, 200, [
            'Content-Type' => 'text/plain',
        ]);
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

        return response()->json([
            'item' => [
                'id' => $item->id,
                'type_name' => $item->type_name,
                'market_name' => $market->name,
                'location_name' => $market->location_name,
            ],
            'details' => $report->itemDetails($item),
            'trend' => $this->itemSalesTrend($item, $days),
            'events' => $events,
        ]);
    }

    public function history(Request $request, MarketSeedingSettings $settings, MarketStockReport $report)
    {
        $days = $this->historyDays($request);
        $markets = $this->visibleMarkets()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $history = $this->filteredVisibleHistory($request)
            ->latest()
            ->paginate(50)
            ->appends($request->only('market_id', 'status', 'type_category', 'days'));

        $chartData = $this->historyChartData($request, $days);
        $salesChartData = $this->salesChartData($request, $days);
        $salesSummary = $this->salesSummary($request, $days);
        $topSoldItems = $this->topSoldItems($request, $days);
        $categorySales = $this->categorySales($request, $days);
        $restockLeaders = $this->restockLeaders($request);
        $typeCategories = $this->historyTypeCategories($request);
        $recommendationMetrics = $this->recommendationMetrics($request, $days);
        $recommendationSalesDays = $settings->recommendationSalesDays();
        $recommendationBufferPercentage = $settings->recommendationBufferPercentage();
        $attentionItems = $this->recommendationRows($request, $days, $recommendationMetrics, $recommendationSalesDays, $recommendationBufferPercentage);
        $this->attachRecommendationEconomics($attentionItems, $report);
        $heatmapData = $this->marketCategoryHeatmap($request, $days);

        $this->attachTypeCategories($history->getCollection());
        $this->attachTypeCategories($restockLeaders);
        $this->attachLatestSnapshotQuantities($topSoldItems);

        $this->attachRecommendations($topSoldItems, $days, $recommendationMetrics, $recommendationSalesDays, $recommendationBufferPercentage);
        $this->attachRecommendations($restockLeaders, $days, $recommendationMetrics, $recommendationSalesDays, $recommendationBufferPercentage);
        $this->attachRecommendations($history->getCollection(), $days, $recommendationMetrics, $recommendationSalesDays, $recommendationBufferPercentage);
        $this->attachRestockEfficiency($restockLeaders, $days);

        return view('seat-market-seeding::history', compact(
            'history',
            'markets',
            'chartData',
            'salesChartData',
            'salesSummary',
            'topSoldItems',
            'categorySales',
            'restockLeaders',
            'attentionItems',
            'heatmapData',
            'typeCategories',
            'days',
            'recommendationSalesDays',
            'recommendationBufferPercentage'
        ));
    }

    public function applyHistoryRecommendations(Request $request, MarketSeedingSettings $settings, StockTargetProjector $projector)
    {
        $days = $this->historyDays($request);
        $recommendationMetrics = $this->recommendationMetrics($request, $days);
        $recommendations = $this->recommendationRows(
            $request,
            $days,
            $recommendationMetrics,
            $settings->recommendationSalesDays(),
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
                    (int) $recommendation->warning_quantity
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

    private function filteredVisibleHistory(Request $request)
    {
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
            ->where('created_at', '>=', now()->subDays($this->historyDays($request) - 1)->startOfDay());
    }

    private function filteredVisibleSnapshots(Request $request, int $days)
    {
        return $this->visibleSnapshots()
            ->when($request->filled('market_id'), function ($query) use ($request) {
                $query->where('market_id', $request->integer('market_id'));
            })
            ->when($request->filled('type_category'), function ($query) use ($request) {
                $query->where('type_category', $request->input('type_category'));
            })
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay());
    }

    private function salesSummary(Request $request, int $days): array
    {
        $summary = $this->filteredVisibleSnapshots($request, $days)
            ->selectRaw('COALESCE(SUM(estimated_sold_quantity), 0) as estimated_sold')
            ->selectRaw('COALESCE(SUM(restocked_quantity), 0) as restocked')
            ->selectRaw('COUNT(DISTINCT CONCAT(market_id, ":", type_id)) as tracked_lines')
            ->selectRaw('COALESCE(SUM(CASE WHEN estimated_sold_quantity > 0 THEN 1 ELSE 0 END), 0) as sales_events')
            ->first();

        $totalSold = (int) optional($summary)->estimated_sold;

        return [
            'estimated_sold' => $totalSold,
            'restocked' => (int) optional($summary)->restocked,
            'tracked_lines' => (int) optional($summary)->tracked_lines,
            'sales_events' => (int) optional($summary)->sales_events,
            'average_daily_sold' => $days > 0 ? round($totalSold / $days, 1) : 0,
        ];
    }

    private function salesChartData(Request $request, int $days): array
    {
        $labels = $this->historyDateLabels($days);
        $events = $this->filteredVisibleSnapshots($request, $days)
            ->selectRaw('DATE(created_at) as event_date')
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
        return $this->filteredVisibleSnapshots($request, $days)
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
            ->selectRaw('MAX(desired_quantity) as target_quantity')
            ->selectRaw('MAX(warning_quantity) as warning_quantity')
            ->selectRaw('SUM(CASE WHEN estimated_sold_quantity > 0 THEN 1 ELSE 0 END) as sales_events')
            ->selectRaw('MAX(CASE WHEN estimated_sold_quantity > 0 THEN created_at ELSE NULL END) as last_sold_at')
            ->groupBy('market_id', 'item_id', 'market_name', 'location_name', 'type_id', 'type_name', 'type_category')
            ->havingRaw('SUM(estimated_sold_quantity) > 0')
            ->orderByDesc('estimated_sold')
            ->orderByDesc('sales_events')
            ->limit(20)
            ->get();
    }

    private function attachLatestSnapshotQuantities($rows): void
    {
        $itemIds = collect($rows)
            ->pluck('item_id')
            ->filter()
            ->unique()
            ->values();

        if ($itemIds->isEmpty()) {
            return;
        }

        $snapshots = MarketStockSnapshot::query()
            ->whereIn('item_id', $itemIds)
            ->latest()
            ->get()
            ->unique('item_id')
            ->keyBy('item_id');

        foreach ($rows as $row) {
            $row->latest_seen_quantity = (int) optional($snapshots->get($row->item_id))->current_quantity;
        }
    }

    private function categorySales(Request $request, int $days)
    {
        return $this->filteredVisibleSnapshots($request, $days)
            ->select('type_category')
            ->selectRaw('SUM(estimated_sold_quantity) as estimated_sold')
            ->groupBy('type_category')
            ->havingRaw('SUM(estimated_sold_quantity) > 0')
            ->orderByDesc('estimated_sold')
            ->limit(8)
            ->get();
    }

    private function recommendationRows(Request $request, int $days, $metrics, int $recommendationSalesDays, int $recommendationBufferPercentage)
    {
        $rows = $this->filteredVisibleSnapshots($request, $days)
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
            ->selectRaw('MAX(desired_quantity) as target_quantity')
            ->selectRaw('MAX(warning_quantity) as warning_quantity')
            ->selectRaw('SUM(CASE WHEN estimated_sold_quantity > 0 THEN 1 ELSE 0 END) as sales_events')
            ->groupBy('market_id', 'item_id', 'market_name', 'location_name', 'type_id', 'type_name', 'type_category')
            ->get();

        $restockMetrics = $this->visibleHistory()
            ->when($request->filled('market_id'), function ($query) use ($request) {
                $query->where('market_id', $request->integer('market_id'));
            })
            ->when($request->filled('type_category'), function ($query) use ($request) {
                $this->applyTypeCategoryFilter($query, $request->input('type_category'));
            })
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->whereIn('current_status', ['low', 'empty'])
            ->select('item_id')
            ->selectRaw('COUNT(*) as restock_events')
            ->selectRaw('SUM(GREATEST(desired_quantity - current_quantity, 0)) as total_shortage')
            ->groupBy('item_id')
            ->get()
            ->keyBy('item_id');

        foreach ($rows as $row) {
            $restock = $restockMetrics->get($row->item_id);
            $row->restock_events = (int) optional($restock)->restock_events;
            $row->total_shortage = (int) optional($restock)->total_shortage;
        }

        $this->attachLatestSnapshotQuantities($rows);
        $this->attachRecommendations($rows, $days, $metrics, $recommendationSalesDays, $recommendationBufferPercentage);
        $this->attachRestockEfficiency($rows, $days);

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
        $labels = $this->historyDateLabels($days);
        $sales = MarketStockSnapshot::query()
            ->where('item_id', $item->id)
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->selectRaw('DATE(created_at) as sale_date')
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
            'days' => $days,
        ];
    }

    private function attachRestockEfficiency($rows, int $days): void
    {
        foreach ($rows as $row) {
            $events = (int) ($row->restock_events ?? 0);
            $row->average_days_between_restock_needs = $events > 0 ? round($days / $events, 1) : null;
        }
    }

    private function attachRecommendationEconomics($rows, MarketStockReport $report): void
    {
        $items = SeededMarketItem::query()
            ->with('market')
            ->whereIn('id', collect($rows)->pluck('item_id')->filter()->unique()->values())
            ->get()
            ->keyBy('id');

        foreach ($rows as $row) {
            $item = $items->get($row->item_id);
            $details = $item ? $report->itemDetails($item) : [];
            $deltaQuantity = max(0, (int) $row->recommended_quantity - (int) $row->current_target_quantity);
            $jitaPrice = (float) ($details['jita_price'] ?? 0);
            $itemVolume = (float) ($details['item_volume'] ?? 0);

            $row->recommendation_delta_quantity = $deltaQuantity;
            $row->recommendation_delta_cost = $deltaQuantity * $jitaPrice;
            $row->recommendation_delta_volume = $deltaQuantity * $itemVolume;
        }
    }

    private function marketCategoryHeatmap(Request $request, int $days): array
    {
        $rows = $this->filteredVisibleSnapshots($request, $days)
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
        return $this->filteredVisibleSnapshots($request, $days)
            ->select('item_id')
            ->selectRaw('SUM(estimated_sold_quantity) as estimated_sold')
            ->selectRaw('SUM(restocked_quantity) as restocked')
            ->selectRaw('SUM(CASE WHEN estimated_sold_quantity > 0 THEN 1 ELSE 0 END) as sales_events')
            ->groupBy('item_id')
            ->get()
            ->keyBy('item_id');
    }

    private function attachRecommendations($rows, int $days, $metrics, int $recommendationSalesDays, int $recommendationBufferPercentage): void
    {
        foreach ($rows as $row) {
            $target = max(1, (int) ($row->target_quantity ?? $row->desired_quantity ?? 1));
            $metric = $metrics->get($row->item_id);
            $estimatedSold = (int) ($row->estimated_sold ?? optional($metric)->estimated_sold ?? 0);
            $dailySold = $days > 0 ? $estimatedSold / $days : 0;
            $bufferMultiplier = 1 + ($recommendationBufferPercentage / 100);
            $salesRecommendation = (int) ceil($dailySold * $recommendationSalesDays * $bufferMultiplier);
            $restockRecommendation = 0;

            if (isset($row->restock_events) && (int) $row->restock_events > 0) {
                $averageShortage = ((int) ($row->total_shortage ?? 0)) / max(1, (int) $row->restock_events);
                $restockRecommendation = (int) ceil($target + $averageShortage);
            }

            $statusRecommendation = in_array($row->current_status ?? null, ['low', 'empty'], true)
                ? (int) ceil($target * $bufferMultiplier)
                : 0;

            $recommended = max(1, $target, $salesRecommendation, $restockRecommendation, $statusRecommendation);
            $row->current_target_quantity = $target;
            $row->recommended_quantity = $recommended;
            $row->recommendation_differs = $recommended !== $target;

            if ($recommended <= $target) {
                $row->recommendation_reason = 'Current target already covers the recent sales pace and observed shortages.';
            } elseif ($recommended === $restockRecommendation) {
                $row->recommendation_reason = 'Based on the current target plus the average shortage seen when this item went low or empty.';
            } elseif ($recommended === $statusRecommendation) {
                $row->recommendation_reason = 'Based on the item currently hitting a low or empty transition, with a ' . $recommendationBufferPercentage . '% target buffer.';
            } else {
                $row->recommendation_reason = 'Based on roughly ' . $recommendationSalesDays . ' days of average estimated sales, plus a ' . $recommendationBufferPercentage . '% buffer.';
            }
        }
    }

    private function historyDays(Request $request): int
    {
        $days = (int) $request->input('days', 30);

        return in_array($days, [7, 30, 60, 90, 180, 365], true) ? $days : 30;
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

        $events = $this->filteredVisibleHistory($request)
            ->get(['current_status', 'created_at'])
            ->groupBy(function (MarketStockHistory $event) {
                return optional($event->created_at)->format('Y-m-d');
            });

        $series = [
            'low' => [],
            'empty' => [],
            'stocked' => [],
        ];

        foreach ($labels as $label) {
            $dayEvents = $events->get($label, collect());

            foreach (array_keys($series) as $status) {
                $series[$status][] = $dayEvents->where('current_status', $status)->count();
            }
        }

        return [
            'labels' => $labels->map(function (string $date) {
                return \Carbon\Carbon::createFromFormat('Y-m-d', $date)->format('M j');
            })->values(),
            'series' => $series,
        ];
    }

    private function restockLeaders(Request $request)
    {
        return $this->visibleHistory()
            ->when($request->filled('market_id'), function ($query) use ($request) {
                $query->where('market_id', $request->integer('market_id'));
            })
            ->when($request->filled('type_category'), function ($query) use ($request) {
                $this->applyTypeCategoryFilter($query, $request->input('type_category'));
            })
            ->where('created_at', '>=', now()->subDays($this->historyDays($request) - 1)->startOfDay())
            ->whereIn('current_status', ['low', 'empty'])
            ->select([
                'market_id',
                'item_id',
                'market_name',
                'location_name',
                'type_id',
                'type_name',
            ])
            ->selectRaw('COUNT(*) as restock_events')
            ->selectRaw("SUM(CASE WHEN current_status = 'empty' THEN 1 ELSE 0 END) as empty_events")
            ->selectRaw("SUM(CASE WHEN current_status = 'low' THEN 1 ELSE 0 END) as low_events")
            ->selectRaw('SUM(GREATEST(desired_quantity - current_quantity, 0)) as total_shortage')
            ->selectRaw('MAX(desired_quantity) as desired_quantity')
            ->selectRaw('MAX(warning_quantity) as warning_quantity')
            ->selectRaw('MAX(created_at) as last_needed_at')
            ->groupBy('market_id', 'item_id', 'market_name', 'location_name', 'type_id', 'type_name')
            ->orderByDesc('restock_events')
            ->orderByDesc('empty_events')
            ->orderByDesc('total_shortage')
            ->get();
    }

    private function historyTypeCategories(Request $request)
    {
        $snapshotCategories = $this->visibleSnapshots()
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
            ->where(function ($query) use ($roleIds) {
                $query->whereNull('role_id')
                    ->orWhereIn('role_id', $roleIds);
            });
    }

    private function visibleSnapshots()
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return MarketStockSnapshot::query();
        }

        $roleIds = $user->roles->pluck('id');

        return MarketStockSnapshot::query()
            ->where(function ($query) use ($roleIds) {
                $query->whereNull('role_id')
                    ->orWhereIn('role_id', $roleIds);
            });
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
        $itemCount = $marketIds->isEmpty()
            ? 0
            : SeededMarketItem::whereIn('market_id', $marketIds)->count();
        $sourceCount = $marketIds->isEmpty()
            ? 0
            : MarketSeedingItemSource::whereIn('market_id', $marketIds)->count();

        return 'seat-market-seeding:dashboard:' . md5(json_encode([
            'version' => 3,
            'user_id' => $user->id,
            'is_admin' => $user->isAdmin(),
            'roles' => $roleIds,
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
}
