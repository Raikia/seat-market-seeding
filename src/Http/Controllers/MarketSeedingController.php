<?php

namespace Raikia\SeatMarketSeeding\Http\Controllers;

use Illuminate\Http\Request;
use Raikia\SeatMarketSeeding\Models\MarketStockHistory;
use Raikia\SeatMarketSeeding\Models\SeededMarket;
use Raikia\SeatMarketSeeding\Services\MarketStockReport;
use Seat\Web\Http\Controllers\Controller;

class MarketSeedingController extends Controller
{
    public function index(MarketStockReport $report)
    {
        $markets = $this->visibleMarkets()
            ->with('items')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $stockReport = $report->build($markets);

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

        return view('seat-market-seeding::history', compact('history', 'markets', 'chartData'));
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
            ->whereNull('role_id')
            ->orWhereIn('role_id', $roleIds);
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
