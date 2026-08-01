<?php

namespace Raikia\SeatMarketSeeding\Tests\Unit\Services;

use Illuminate\Support\Facades\DB;
use Raikia\SeatMarketSeeding\Models\MarketSeedingNotificationGroupFilter;
use Raikia\SeatMarketSeeding\Models\MarketStockSnapshot;
use Raikia\SeatMarketSeeding\Models\SeededMarketItem;
use Raikia\SeatMarketSeeding\Services\MarketStockTransitionNotifier;
use Raikia\SeatMarketSeeding\Tests\TestCase;
use Seat\Notifications\Models\NotificationGroup;

class MarketStockTransitionNotifierTest extends TestCase
{
    public function test_notification_groups_are_filtered_by_market_when_configured(): void
    {
        $homeMarket = $this->createMarket([
            'name' => 'Home',
            'location_id' => 60000001,
            'location_name' => 'Home Station',
        ]);
        $awayMarket = $this->createMarket([
            'name' => 'Away',
            'location_id' => 60000002,
            'location_name' => 'Away Station',
        ]);

        $allMarketsGroup = NotificationGroup::create(['name' => 'All Markets']);
        $homeOnlyGroup = NotificationGroup::create(['name' => 'Home Only']);
        $awayOnlyGroup = NotificationGroup::create(['name' => 'Away Only']);
        $unrelatedGroup = NotificationGroup::create(['name' => 'Unrelated']);

        foreach ([$allMarketsGroup, $homeOnlyGroup, $awayOnlyGroup] as $group) {
            DB::table('group_alerts')->insert([
                'notification_group_id' => $group->id,
                'alert' => MarketStockTransitionNotifier::ALERT_LOW_STOCK,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('group_alerts')->insert([
            'notification_group_id' => $unrelatedGroup->id,
            'alert' => MarketStockTransitionNotifier::ALERT_RESTOCKED,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        MarketSeedingNotificationGroupFilter::create([
            'notification_group_id' => $homeOnlyGroup->id,
            'allowed_market_ids' => [$homeMarket->id],
        ]);
        MarketSeedingNotificationGroupFilter::create([
            'notification_group_id' => $awayOnlyGroup->id,
            'allowed_market_ids' => [$awayMarket->id],
        ]);

        $method = new \ReflectionMethod(MarketStockTransitionNotifier::class, 'notificationGroupsForMarket');
        $method->setAccessible(true);
        $groups = $method->invoke(
            app(MarketStockTransitionNotifier::class),
            MarketStockTransitionNotifier::ALERT_LOW_STOCK,
            $homeMarket
        );

        $this->assertSame(
            ['All Markets', 'Home Only'],
            $groups->pluck('name')->values()->all()
        );
    }

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
