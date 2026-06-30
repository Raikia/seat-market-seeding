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
use Raikia\SeatMarketSeeding\Services\StockTargetQuantity;
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
                'type_name' => $item->type_name,
                'market_name' => $market->name,
                'location_name' => $market->location_name,
            ],
            'details' => $report->itemDetails($item),
            'trend' => $this->itemSalesTrend($item, $days),
            'events' => $events,
            'target_history' => $targetHistory,
        ]);
    }

    public function history(Request $request, MarketSeedingSettings $settings, MarketStockReport $report)
    {
        $days = $this->historyDays($request);
        $markets = $this->visibleMarkets()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

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
        $historyAjaxUrl = route('market-seeding.history.transitions', $request->only('market_id', 'status', 'type_category', 'days'));

        $this->attachTypeCategories($restockLeaders);
        $this->attachCurrentItemValues($topSoldItems);

        $this->attachRecommendations($topSoldItems, $days, $recommendationMetrics, $recommendationSalesDays, $recommendationBufferPercentage);
        $this->attachRecommendations($restockLeaders, $days, $recommendationMetrics, $recommendationSalesDays, $recommendationBufferPercentage);
        $this->attachRestockEfficiency($restockLeaders, $days);

        return view('seat-market-seeding::history', compact(
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
            'recommendationBufferPercentage',
            'historyAjaxUrl'
        ));
    }

    public function historyTransitions(Request $request, MarketSeedingSettings $settings)
    {
        $days = $this->historyDays($request);
        $query = $this->filteredVisibleHistory($request);
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

        $recommendationMetrics = $this->recommendationMetrics($request, $days);
        $this->attachRecommendations(
            $events,
            $days,
            $recommendationMetrics,
            $settings->recommendationSalesDays(),
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

    private function salesSummary(Request $request, int $days): array
    {
        $summary = $this->filteredVisibleSummaries($request, $days)
            ->selectRaw('COALESCE(SUM(estimated_sold_quantity), 0) as estimated_sold')
            ->selectRaw('COALESCE(SUM(restocked_quantity), 0) as restocked')
            ->selectRaw('COUNT(DISTINCT CONCAT(market_id, ":", type_id)) as tracked_lines')
            ->selectRaw('COALESCE(SUM(sales_events), 0) as sales_events')
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
            } elseif ($summary) {
                $row->target_quantity = (int) $summary->latest_desired_quantity;
                $row->desired_quantity = (int) $summary->latest_desired_quantity;
                $row->warning_quantity = (int) $summary->latest_warning_quantity;
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

    private function recommendationRows(Request $request, int $days, $metrics, int $recommendationSalesDays, int $recommendationBufferPercentage)
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

        $restockMetrics = $this->filteredVisibleHistory($request)
            ->whereIn('current_status', ['low', 'empty'])
            ->select('item_id')
            ->selectRaw('COUNT(*) as restock_events')
            ->selectRaw('SUM(GREATEST(desired_quantity - current_quantity, 0)) as total_shortage')
            ->selectRaw('CEIL(AVG(desired_quantity + GREATEST(desired_quantity - current_quantity, 0))) as restock_recommended_quantity')
            ->groupBy('item_id')
            ->get()
            ->keyBy('item_id');

        foreach ($rows as $row) {
            $restock = $restockMetrics->get($row->item_id);
            $row->restock_events = (int) optional($restock)->restock_events;
            $row->total_shortage = (int) optional($restock)->total_shortage;
            $row->restock_recommended_quantity = (int) optional($restock)->restock_recommended_quantity;
        }

        $this->attachCurrentItemValues($rows);
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
        $sales = MarketStockDailySummary::query()
            ->where('item_id', $item->id)
            ->where('summary_date', '>=', now()->subDays($days - 1)->toDateString())
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

            if (isset($row->restock_recommended_quantity) && (int) $row->restock_recommended_quantity > 0) {
                $restockRecommendation = (int) $row->restock_recommended_quantity;
            }

            $recommended = max(1, $target, $salesRecommendation, $restockRecommendation);
            $row->current_target_quantity = $target;
            $row->recommended_quantity = $recommended;
            $row->recommendation_differs = $recommended !== $target;

            if ($recommended <= $target) {
                $row->recommendation_reason = 'Current target already covers the recent sales pace and observed shortages.';
            } elseif ($recommended === $restockRecommendation) {
                $row->recommendation_reason = 'Based on the current target plus the average shortage seen when this item went low or empty.';
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

    private function restockLeaders(Request $request)
    {
        return $this->filteredVisibleSummaries($request, $this->historyDays($request))
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
        $itemHtml = e($event->type_name)
            . '<div class="text-muted small">' . e($event->type_category ?: 'Unknown') . '</div>';

        if ($event->recommendation_differs) {
            $itemHtml .= '<div class="history-recommendation-pill">Target '
                . number_format((int) $event->current_target_quantity)
                . ' &rarr; Recommended '
                . number_format((int) $event->recommended_quantity)
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
                . ' data-history-url="' . e(route('market-seeding.items.history', ['item' => $event->item_id, 'days' => request('days', 30)])) . '"'
                . ' data-desired-quantity="' . (int) $event->desired_quantity . '"'
                . ' data-warning-quantity="' . (int) $event->warning_quantity . '"'
                . ' data-recommended-quantity="' . (int) $event->recommended_quantity . '"'
                . ' data-recommendation-reason="' . e($event->recommendation_reason) . '">'
                . '<i class="fas ' . ($canManage ? 'fa-edit' : 'fa-eye') . '"></i></button></span>'
            : '<span class="float-right">-</span>';

        return $row;
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

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
