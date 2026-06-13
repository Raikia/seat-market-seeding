<?php

namespace Raikia\SeatMarketSeeding\Http\Controllers;

use Raikia\SeatMarketSeeding\Models\SeededMarket;
use Raikia\SeatMarketSeeding\Services\MarketStockReport;
use Seat\Web\Http\Controllers\Controller;

class MarketSeedingController extends Controller
{
    public function index(MarketStockReport $report)
    {
        $markets = $this->visibleMarkets()
            ->with('items')
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
