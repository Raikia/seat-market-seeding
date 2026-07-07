<?php

namespace Raikia\SeatMarketSeeding\Tests\Unit\Services;

use Illuminate\Support\Facades\DB;
use Raikia\SeatMarketSeeding\Models\MarketSeedingTrackedDoctrine;
use Raikia\SeatMarketSeeding\Services\MarketStockReport;
use Raikia\SeatMarketSeeding\Services\StockTargetPreviewer;
use Raikia\SeatMarketSeeding\Services\StockTargetProjector;
use Raikia\SeatMarketSeeding\Tests\TestCase;

class StockTargetPreviewerTest extends TestCase
{
    public function test_bulk_preview_reports_new_increase_reduce_and_unchanged_actions(): void
    {
        $market = $this->createMarket();
        $projector = app(StockTargetProjector::class);

        $projector->setManualTarget($market, 1, 'Existing Same', 10, 3);
        $projector->setManualTarget($market, 2, 'Existing Increase', 10, 3);
        $projector->setManualTarget($market, 3, 'Existing Reduce', 10, 3);

        $preview = app(StockTargetPreviewer::class)->preview($market->fresh(), [
            ['type_id' => 1, 'type_name' => 'Existing Same', 'quantity' => 10],
            ['type_id' => 2, 'type_name' => 'Existing Increase', 'quantity' => 20],
            ['type_id' => 3, 'type_name' => 'Existing Reduce', 'quantity' => 5],
            ['type_id' => 4, 'type_name' => 'New Thing', 'quantity' => 2],
        ], 'replace', false, 50);

        $actions = collect($preview['rows'])->pluck('action', 'type_name');

        $this->assertSame('unchanged', $actions['Existing Same']);
        $this->assertSame('replace', $actions['Existing Increase']);
        $this->assertSame('reduce', $actions['Existing Reduce']);
        $this->assertSame('new', $actions['New Thing']);
        $this->assertSame(4, $preview['summary']['total']);
    }

    public function test_bulk_preview_reports_delta_cost_and_volume(): void
    {
        $market = $this->createMarket();
        $projector = app(StockTargetProjector::class);

        DB::table('invTypes')->insert([
            'typeID' => 1001,
            'typeName' => 'Existing Module',
            'volume' => 5,
            'published' => true,
        ]);
        DB::table('market_orders')->insert([
            'order_id' => 1000001,
            'location_id' => MarketStockReport::JITA_STATION_ID,
            'type_id' => 1001,
            'volume_remaining' => 10,
            'volume_total' => 10,
            'price' => 2500000,
            'is_buy_order' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $projector->setManualTarget($market, 1001, 'Existing Module', 2, 1);

        $preview = app(StockTargetPreviewer::class)->preview($market->fresh(), [
            ['type_id' => 1001, 'type_name' => 'Existing Module', 'quantity' => 5],
        ], 'replace', false, 50);
        $row = collect($preview['rows'])->first();

        $this->assertSame(3, $row['delta_quantity']);
        $this->assertSame(7500000.0, $row['delta_cost']);
        $this->assertSame(15.0, $row['delta_volume']);
        $this->assertSame(7500000.0, $preview['summary']['delta_cost']);
        $this->assertSame(15.0, $preview['summary']['delta_volume']);
    }

    public function test_doctrine_preview_shows_items_removed_from_existing_tracking(): void
    {
        $market = $this->createMarket();
        $projector = app(StockTargetProjector::class);
        $doctrine = MarketSeedingTrackedDoctrine::create([
            'market_id' => $market->id,
            'doctrine_id' => 42,
            'doctrine_name' => 'Attackers',
            'multiplier' => 10,
            'warning_percentage' => 33,
            'merge_mode' => MarketSeedingTrackedDoctrine::MERGE_MAX,
            'fit_aggregation_mode' => MarketSeedingTrackedDoctrine::FIT_AGGREGATION_MAX,
        ]);

        $projector->replaceDoctrineTargets($doctrine, [
            ['type_id' => 1, 'type_name' => 'Old Module', 'quantity' => 10],
            ['type_id' => 2, 'type_name' => 'Kept Module', 'quantity' => 10],
        ]);

        $preview = app(StockTargetPreviewer::class)->previewDoctrine(
            $market->fresh(),
            42,
            'Attackers',
            [['type_id' => 2, 'type_name' => 'Kept Module', 'quantity' => 10]],
            33,
            MarketSeedingTrackedDoctrine::MERGE_MAX
        );

        $rows = collect($preview['rows'])->keyBy('type_name');

        $this->assertSame('remove', $rows['Old Module']['action']);
        $this->assertSame(0, $rows['Old Module']['new_quantity']);
        $this->assertSame('unchanged', $rows['Kept Module']['action']);
    }
}
