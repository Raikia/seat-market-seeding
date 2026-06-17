<?php

namespace Raikia\SeatMarketSeeding\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Raikia\SeatMarketSeeding\Models\MarketSeedingItemSource;
use Raikia\SeatMarketSeeding\Models\MarketStockHistory;
use Raikia\SeatMarketSeeding\Models\SeededMarket;
use Raikia\SeatMarketSeeding\Models\SeededMarketItem;
use Raikia\SeatMarketSeeding\Services\MarketStockReport;
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

    public function history(Request $request)
    {
        $markets = $this->visibleMarkets()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $history = $this->filteredVisibleHistory($request)
            ->latest()
            ->paginate(50)
            ->appends($request->only('market_id', 'status'));

        $chartData = $this->historyChartData($request);
        $restockLeaders = $this->restockLeaders($request);

        return view('seat-market-seeding::history', compact('history', 'markets', 'chartData', 'restockLeaders'));
    }

    private function filteredVisibleHistory(Request $request)
    {
        return $this->visibleHistory()
            ->when($request->filled('market_id'), function ($query) use ($request) {
                $query->where('market_id', $request->integer('market_id'));
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('current_status', $request->input('status'));
            });
    }

    private function historyChartData(Request $request): array
    {
        $labels = collect(range(29, 0))
            ->map(function (int $daysAgo) {
                return now()->subDays($daysAgo)->format('Y-m-d');
            });

        $events = $this->filteredVisibleHistory($request)
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
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
            ->whereIn('current_status', ['low', 'empty'])
            ->select([
                'market_id',
                'market_name',
                'location_name',
                'type_id',
                'type_name',
            ])
            ->selectRaw('COUNT(*) as restock_events')
            ->selectRaw("SUM(CASE WHEN current_status = 'empty' THEN 1 ELSE 0 END) as empty_events")
            ->selectRaw("SUM(CASE WHEN current_status = 'low' THEN 1 ELSE 0 END) as low_events")
            ->selectRaw('SUM(GREATEST(desired_quantity - current_quantity, 0)) as total_shortage')
            ->selectRaw('MAX(created_at) as last_needed_at')
            ->groupBy('market_id', 'market_name', 'location_name', 'type_id', 'type_name')
            ->orderByDesc('restock_events')
            ->orderByDesc('empty_events')
            ->orderByDesc('total_shortage')
            ->limit(15)
            ->get();
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
