<?php

namespace Raikia\SeatMarketSeeding\Tests\Unit\Services;

use Raikia\SeatMarketSeeding\Models\MarketSeedingItemSource;
use Raikia\SeatMarketSeeding\Models\MarketSeedingTargetHistory;
use Raikia\SeatMarketSeeding\Models\MarketSeedingTrackedDoctrine;
use Raikia\SeatMarketSeeding\Models\SeededMarketItem;
use Raikia\SeatMarketSeeding\Services\StockTargetImporter;
use Raikia\SeatMarketSeeding\Services\StockTargetProjector;
use Raikia\SeatMarketSeeding\Tests\TestCase;

class StockTargetProjectorTest extends TestCase
{
    public function test_manual_import_adds_instead_of_overwriting_and_logs_history(): void
    {
        $market = $this->createMarket();
        $importer = app(StockTargetImporter::class);

        $importer->import($market, [[
            'type_id' => 621,
            'type_name' => 'Caracal',
            'quantity' => 50,
        ]], 'add', false, 33);

        $importer->import($market, [[
            'type_id' => 621,
            'type_name' => 'Caracal',
            'quantity' => 10,
        ]], 'add', false, 33);

        $item = SeededMarketItem::where('market_id', $market->id)->where('type_id', 621)->firstOrFail();

        $this->assertSame(60, $item->desired_quantity);
        $this->assertSame(20, $item->warning_quantity);
        $this->assertSame(2, MarketSeedingTargetHistory::where('item_id', $item->id)->count());
        $this->assertSame(
            MarketSeedingTargetHistory::CHANGE_BULK_IMPORT,
            MarketSeedingTargetHistory::where('item_id', $item->id)->latest('id')->first()->change_type
        );
    }

    public function test_keep_higher_import_does_not_lower_existing_manual_target(): void
    {
        $market = $this->createMarket();
        $importer = app(StockTargetImporter::class);

        $importer->import($market, [[
            'type_id' => 3244,
            'type_name' => 'Warp Scrambler II',
            'quantity' => 50,
        ]], 'add', false, 33);

        $importer->import($market, [[
            'type_id' => 3244,
            'type_name' => 'Warp Scrambler II',
            'quantity' => 1,
        ]], 'add', true, 33);

        $item = SeededMarketItem::where('market_id', $market->id)->where('type_id', 3244)->firstOrFail();

        $this->assertSame(50, $item->desired_quantity);
        $this->assertSame(17, $item->warning_quantity);
    }

    public function test_manual_adjustment_above_doctrine_remains_after_doctrine_is_removed(): void
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

        $projector->replaceDoctrineTargets($doctrine, [[
            'type_id' => 621,
            'type_name' => 'Caracal',
            'quantity' => 10,
        ]]);

        $item = SeededMarketItem::where('market_id', $market->id)->where('type_id', 621)->firstOrFail();
        $projector->setEffectiveTarget($item, 15, 5);

        $doctrine->sources()->delete();
        $doctrine->delete();
        $projector->recalculateMarket($market, MarketSeedingTargetHistory::CHANGE_DOCTRINE);

        $item = SeededMarketItem::where('market_id', $market->id)->where('type_id', 621)->firstOrFail();

        $this->assertSame(5, $item->desired_quantity);
        $this->assertSame(5, $item->warning_quantity);
        $this->assertTrue(
            MarketSeedingItemSource::where('market_id', $market->id)
                ->where('type_id', 621)
                ->where('source_type', MarketSeedingItemSource::SOURCE_MANUAL_ADJUSTMENT)
                ->exists()
        );
    }

    public function test_non_doctrine_manual_target_can_be_lowered(): void
    {
        $market = $this->createMarket();
        $projector = app(StockTargetProjector::class);

        $item = $projector->setManualTarget($market, 621, 'Caracal', 50, 17);
        $item = $projector->setEffectiveTarget($item, 10, 3);

        $this->assertSame(10, $item->desired_quantity);
        $this->assertSame(3, $item->warning_quantity);
        $this->assertSame(10, MarketSeedingItemSource::where('item_id', $item->id)->first()->quantity);
    }

    public function test_clear_style_recalculation_logs_removed_target(): void
    {
        $market = $this->createMarket();
        $projector = app(StockTargetProjector::class);

        $item = $projector->setManualTarget($market, 621, 'Caracal', 50, 17);
        $market->itemSources()->delete();
        $projector->recalculateMarket($market, MarketSeedingTargetHistory::CHANGE_CLEAR);

        $this->assertFalse(SeededMarketItem::whereKey($item->id)->exists());

        $history = MarketSeedingTargetHistory::where('type_id', 621)->latest('id')->firstOrFail();

        $this->assertSame(50, $history->old_target_quantity);
        $this->assertSame(0, $history->new_target_quantity);
        $this->assertSame(MarketSeedingTargetHistory::CHANGE_CLEAR, $history->change_type);
    }
}
