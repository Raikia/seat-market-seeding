<?php

namespace Raikia\SeatMarketSeeding\Tests\Unit\Services;

use Raikia\SeatMarketSeeding\Models\MarketStockSnapshot;
use Raikia\SeatMarketSeeding\Models\SeededMarketItem;
use Raikia\SeatMarketSeeding\Services\MarketStockTransitionNotifier;
use Raikia\SeatMarketSeeding\Tests\TestCase;

class MarketStockTransitionNotifierTest extends TestCase
{
    public function test_expired_stale_quantity_is_not_counted_as_estimated_sold(): void
    {
        $market = $this->createMarket([
            'name' => 'Home',
            'location_id' => 60000001,
            'location_name' => 'Home Station',
            'role_id' => null,
        ]);
        $item = SeededMarketItem::create([
            'market_id' => $market->id,
            'type_id' => 123,
            'type_name' => 'Test Module',
            'desired_quantity' => 20,
            'warning_quantity' => 5,
        ]);
        $previousSnapshot = MarketStockSnapshot::create([
            'market_id' => $market->id,
            'item_id' => $item->id,
            'role_id' => null,
            'type_id' => $item->type_id,
            'market_name' => $market->name,
            'location_name' => $market->location_name,
            'type_name' => $item->type_name,
            'type_category' => 'Modules',
            'previous_quantity' => null,
            'current_quantity' => 10,
            'estimated_sold_quantity' => 0,
            'restocked_quantity' => 0,
            'warning_quantity' => 5,
            'desired_quantity' => 20,
        ]);

        $method = new \ReflectionMethod(MarketStockTransitionNotifier::class, 'recordSnapshot');
        $method->setAccessible(true);
        $snapshot = $method->invoke(
            app(MarketStockTransitionNotifier::class),
            $market,
            $item,
            $previousSnapshot,
            2,
            5
        );

        $this->assertSame(10, $snapshot->previous_quantity);
        $this->assertSame(2, $snapshot->current_quantity);
        $this->assertSame(3, $snapshot->estimated_sold_quantity);
        $this->assertSame(0, $snapshot->restocked_quantity);
    }

    public function test_expired_stale_quantity_never_makes_estimated_sold_negative(): void
    {
        $market = $this->createMarket([
            'name' => 'Home',
            'location_id' => 60000001,
            'location_name' => 'Home Station',
            'role_id' => null,
        ]);
        $item = SeededMarketItem::create([
            'market_id' => $market->id,
            'type_id' => 123,
            'type_name' => 'Test Module',
            'desired_quantity' => 20,
            'warning_quantity' => 5,
        ]);
        $previousSnapshot = MarketStockSnapshot::create([
            'market_id' => $market->id,
            'item_id' => $item->id,
            'role_id' => null,
            'type_id' => $item->type_id,
            'market_name' => $market->name,
            'location_name' => $market->location_name,
            'type_name' => $item->type_name,
            'type_category' => 'Modules',
            'previous_quantity' => null,
            'current_quantity' => 10,
            'estimated_sold_quantity' => 0,
            'restocked_quantity' => 0,
            'warning_quantity' => 5,
            'desired_quantity' => 20,
        ]);

        $method = new \ReflectionMethod(MarketStockTransitionNotifier::class, 'recordSnapshot');
        $method->setAccessible(true);
        $snapshot = $method->invoke(
            app(MarketStockTransitionNotifier::class),
            $market,
            $item,
            $previousSnapshot,
            8,
            5
        );

        $this->assertSame(0, $snapshot->estimated_sold_quantity);
    }
}
