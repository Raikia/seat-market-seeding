<?php

namespace Raikia\SeatMarketSeeding\Tests\Unit\Http;

use Illuminate\Support\Facades\Queue;
use Raikia\SeatMarketSeeding\Http\Controllers\SettingsController;
use Raikia\SeatMarketSeeding\Jobs\RefreshMarketSeedingMarkets;
use Raikia\SeatMarketSeeding\Models\MarketSeedingTargetHistory;
use Raikia\SeatMarketSeeding\Models\MarketStockDailySummary;
use Raikia\SeatMarketSeeding\Models\MarketStockHistory;
use Raikia\SeatMarketSeeding\Models\MarketStockSnapshot;
use Raikia\SeatMarketSeeding\Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    public function test_refresh_markets_queues_refresh_job(): void
    {
        Queue::fake();

        app(SettingsController::class)->refreshMarkets();

        Queue::assertPushed(RefreshMarketSeedingMarkets::class);
    }

    public function test_clear_stock_history_does_not_clear_target_audit_history(): void
    {
        $market = $this->createMarket();

        MarketStockHistory::create([
            'market_id' => $market->id,
            'type_id' => 1,
            'type_name' => 'Caracal',
            'current_status' => 'low',
        ]);
        MarketStockSnapshot::create([
            'market_id' => $market->id,
            'item_id' => 1,
            'type_id' => 1,
        ]);
        MarketStockDailySummary::create([
            'summary_date' => now()->toDateString(),
            'market_id' => $market->id,
            'type_id' => 1,
            'type_name' => 'Caracal',
        ]);
        MarketSeedingTargetHistory::create([
            'market_id' => $market->id,
            'type_id' => 1,
            'type_name' => 'Caracal',
            'new_target_quantity' => 10,
            'change_type' => MarketSeedingTargetHistory::CHANGE_MANUAL,
        ]);

        app(SettingsController::class)->clearHistory();

        $this->assertSame(0, MarketStockHistory::count());
        $this->assertSame(0, MarketStockSnapshot::count());
        $this->assertSame(0, MarketStockDailySummary::count());
        $this->assertSame(1, MarketSeedingTargetHistory::count());
    }

    public function test_clear_audit_history_does_not_clear_stock_history(): void
    {
        $market = $this->createMarket();

        MarketStockHistory::create([
            'market_id' => $market->id,
            'type_id' => 1,
            'type_name' => 'Caracal',
            'current_status' => 'low',
        ]);
        MarketSeedingTargetHistory::create([
            'market_id' => $market->id,
            'type_id' => 1,
            'type_name' => 'Caracal',
            'new_target_quantity' => 10,
            'change_type' => MarketSeedingTargetHistory::CHANGE_MANUAL,
        ]);

        app(SettingsController::class)->clearAuditHistory();

        $this->assertSame(1, MarketStockHistory::count());
        $this->assertSame(0, MarketSeedingTargetHistory::count());
    }
}
