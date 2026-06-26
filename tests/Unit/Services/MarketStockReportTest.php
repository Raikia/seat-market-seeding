<?php

namespace Raikia\SeatMarketSeeding\Tests\Unit\Services;

use Illuminate\Support\Facades\DB;
use Raikia\SeatMarketSeeding\Models\MarketStockDailySummary;
use Raikia\SeatMarketSeeding\Services\MarketStockReport;
use Raikia\SeatMarketSeeding\Services\StockTargetProjector;
use Raikia\SeatMarketSeeding\Tests\TestCase;

class MarketStockReportTest extends TestCase
{
    public function test_build_calculates_quantities_values_health_and_packaged_ship_volume(): void
    {
        $this->seedSde();
        $this->seedType(621, 'Caracal', ['groupID' => 25, 'volume' => 112000]);

        $market = $this->createMarket(['location_id' => 60000001]);
        $item = app(StockTargetProjector::class)->setManualTarget($market, 621, 'Caracal', 10, 4);

        DB::table('market_orders')->insert([
            [
                'order_id' => 1,
                'location_id' => 60000001,
                'type_id' => 621,
                'volume_remaining' => 6,
                'price' => 12000000,
                'is_buy_order' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => 2,
                'location_id' => MarketStockReport::JITA_STATION_ID,
                'type_id' => 621,
                'volume_remaining' => 100,
                'price' => 10000000,
                'is_buy_order' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $report = app(MarketStockReport::class)->build(collect([$market->fresh()]));
        $marketReport = $report['markets'][0];
        $row = $marketReport['rows']->first();

        $this->assertSame($item->id, $row['item']->id);
        $this->assertSame(6, $row['current_quantity']);
        $this->assertSame(4, $row['missing_quantity']);
        $this->assertSame(10000000.0, $row['jita_price']);
        $this->assertSame(12000000.0, $row['local_price']);
        $this->assertSame(40000000.0, $row['restock_cost']);
        $this->assertSame(40000.0, $row['restock_volume']);
        $this->assertSame(60.0, $marketReport['totals']['health_score']);
        $this->assertSame("Caracal\t4", $marketReport['export']);
    }

    public function test_item_details_uses_latest_summary_quantity_when_no_local_order_exists(): void
    {
        $this->seedSde();
        $this->seedType(3244, 'Warp Scrambler II', ['groupID' => 53, 'volume' => 5]);

        $market = $this->createMarket(['location_id' => 60000001]);
        $item = app(StockTargetProjector::class)->setManualTarget($market, 3244, 'Warp Scrambler II', 20, 7);

        DB::table('market_orders')->insert([
            'order_id' => 1,
            'location_id' => MarketStockReport::JITA_STATION_ID,
            'type_id' => 3244,
            'volume_remaining' => 100,
            'price' => 500000,
            'is_buy_order' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        MarketStockDailySummary::create([
            'summary_date' => now()->toDateString(),
            'market_id' => $market->id,
            'item_id' => $item->id,
            'type_id' => 3244,
            'market_name' => $market->name,
            'location_name' => $market->location_name,
            'type_name' => $item->type_name,
            'type_category' => 'Modules',
            'latest_current_quantity' => 12,
            'latest_desired_quantity' => 20,
            'latest_warning_quantity' => 7,
        ]);

        $details = app(MarketStockReport::class)->itemDetails($item->fresh());

        $this->assertSame(12, $details['current_quantity']);
        $this->assertSame(8, $details['missing_quantity']);
        $this->assertSame(500000.0, $details['jita_price']);
        $this->assertNull($details['local_price']);
        $this->assertTrue($details['market_price_estimated_from_jita']);
        $this->assertSame(6000000.0, $details['seeded_value']);
        $this->assertSame(4000000.0, $details['restock_cost']);
        $this->assertSame(40.0, $details['restock_volume']);
    }
}
