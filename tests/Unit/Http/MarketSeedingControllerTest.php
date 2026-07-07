<?php

namespace Raikia\SeatMarketSeeding\Tests\Unit\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Raikia\SeatMarketSeeding\Http\Controllers\MarketSeedingController;
use Raikia\SeatMarketSeeding\Models\MarketSeedingItemSource;
use Raikia\SeatMarketSeeding\Models\MarketStockDailySummary;
use Raikia\SeatMarketSeeding\Models\MarketStockHistory;
use Raikia\SeatMarketSeeding\Models\SeededMarketItem;
use Raikia\SeatMarketSeeding\Services\MarketSeedingSettings;
use Raikia\SeatMarketSeeding\Services\MarketOrderExpiryWarnings;
use Raikia\SeatMarketSeeding\Services\MarketStockReport;
use Raikia\SeatMarketSeeding\Services\MarketTargetRecommendations;
use Raikia\SeatMarketSeeding\Services\StockTargetProjector;
use Raikia\SeatMarketSeeding\Tests\TestCase;

class MarketSeedingControllerTest extends TestCase
{
    public function test_history_defaults_to_ninety_days(): void
    {
        $request = Request::create('/market-seeding/history', 'GET');
        app()->instance('request', $request);

        $view = app(MarketSeedingController::class)->history(
            $request,
            app(MarketSeedingSettings::class)
        );

        $this->assertSame(90, $view->getData()['days']);
    }

    public function test_history_average_daily_sold_uses_days_with_data_not_selected_empty_range(): void
    {
        $market = $this->createMarket();
        $item = SeededMarketItem::create([
            'market_id' => $market->id,
            'type_id' => 3244,
            'type_name' => 'Warp Scrambler II',
            'desired_quantity' => 100,
            'warning_quantity' => 33,
        ]);

        MarketStockDailySummary::create([
            'summary_date' => now()->subDays(9)->toDateString(),
            'market_id' => $market->id,
            'item_id' => $item->id,
            'type_id' => $item->type_id,
            'market_name' => $market->name,
            'location_name' => $market->location_name,
            'type_name' => $item->type_name,
            'type_category' => 'Modules',
            'estimated_sold_quantity' => 40,
            'sales_events' => 1,
            'latest_current_quantity' => 80,
            'latest_desired_quantity' => 100,
            'latest_warning_quantity' => 33,
        ]);

        MarketStockDailySummary::create([
            'summary_date' => now()->toDateString(),
            'market_id' => $market->id,
            'item_id' => $item->id,
            'type_id' => $item->type_id,
            'market_name' => $market->name,
            'location_name' => $market->location_name,
            'type_name' => $item->type_name,
            'type_category' => 'Modules',
            'estimated_sold_quantity' => 60,
            'sales_events' => 1,
            'latest_current_quantity' => 20,
            'latest_desired_quantity' => 100,
            'latest_warning_quantity' => 33,
        ]);
        DB::table('market_prices')->insert([
            'type_id' => $item->type_id,
            'average_price' => 0,
            'sell_price' => 1000,
            'adjusted_price' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('/market-seeding/history', 'GET', ['days' => 365]);
        app()->instance('request', $request);

        $view = app(MarketSeedingController::class)->history(
            $request,
            app(MarketSeedingSettings::class)
        );

        $this->assertSame(365, $view->getData()['days']);
        $this->assertSame(10, $view->getData()['historyCoverageDays']);
        $this->assertSame(10.0, $view->getData()['salesSummary']['average_daily_sold']);
        $this->assertSame(10000.0, $view->getData()['globalMetrics']['average_daily_sold_value']);
        $this->assertCount(10, $view->getData()['salesChartData']['labels']);
        $this->assertCount(10, $view->getData()['chartData']['labels']);
    }

    public function test_history_recommendations_ignore_low_stock_shortage_without_sales(): void
    {
        $market = $this->createMarket();
        $item = SeededMarketItem::create([
            'market_id' => $market->id,
            'type_id' => 2048,
            'type_name' => 'Damage Control II',
            'desired_quantity' => 25,
            'warning_quantity' => 8,
        ]);

        MarketStockDailySummary::create([
            'summary_date' => now()->toDateString(),
            'market_id' => $market->id,
            'item_id' => $item->id,
            'type_id' => $item->type_id,
            'market_name' => $market->name,
            'location_name' => $market->location_name,
            'type_name' => $item->type_name,
            'type_category' => 'Modules',
            'estimated_sold_quantity' => 0,
            'sales_events' => 0,
            'latest_current_quantity' => 0,
            'latest_desired_quantity' => 25,
            'latest_warning_quantity' => 8,
        ]);

        MarketStockHistory::create([
            'market_id' => $market->id,
            'item_id' => $item->id,
            'type_id' => $item->type_id,
            'market_name' => $market->name,
            'location_name' => $market->location_name,
            'type_name' => $item->type_name,
            'previous_status' => 'unknown',
            'current_status' => 'empty',
            'current_quantity' => 0,
            'warning_quantity' => 8,
            'desired_quantity' => 25,
        ]);

        $request = Request::create('/market-seeding/history', 'GET', ['days' => 90]);
        app()->instance('request', $request);

        $view = app(MarketSeedingController::class)->history(
            $request,
            app(MarketSeedingSettings::class)
        );

        $this->assertArrayNotHasKey('attentionItems', $view->getData());
        $this->assertTrue($view->getData()['topSoldItems']->isEmpty());
    }

    public function test_history_recommendations_are_based_on_estimated_sales_window_and_buffer(): void
    {
        $market = $this->createMarket();
        $item = SeededMarketItem::create([
            'market_id' => $market->id,
            'type_id' => 1422,
            'type_name' => 'Small Processor Overclocking Unit II',
            'desired_quantity' => 100,
            'warning_quantity' => 33,
        ]);

        MarketStockDailySummary::create([
            'summary_date' => now()->subDays(9)->toDateString(),
            'market_id' => $market->id,
            'item_id' => $item->id,
            'type_id' => $item->type_id,
            'market_name' => $market->name,
            'location_name' => $market->location_name,
            'type_name' => $item->type_name,
            'type_category' => 'Modules',
            'estimated_sold_quantity' => 50,
            'sales_events' => 1,
            'latest_current_quantity' => 75,
            'latest_desired_quantity' => 100,
            'latest_warning_quantity' => 33,
        ]);

        MarketStockDailySummary::create([
            'summary_date' => now()->toDateString(),
            'market_id' => $market->id,
            'item_id' => $item->id,
            'type_id' => $item->type_id,
            'market_name' => $market->name,
            'location_name' => $market->location_name,
            'type_name' => $item->type_name,
            'type_category' => 'Modules',
            'estimated_sold_quantity' => 50,
            'sales_events' => 1,
            'latest_current_quantity' => 50,
            'latest_desired_quantity' => 100,
            'latest_warning_quantity' => 33,
        ]);

        $request = Request::create('/market-seeding/history', 'GET', ['days' => 365]);
        app()->instance('request', $request);

        $view = app(MarketSeedingController::class)->history(
            $request,
            app(MarketSeedingSettings::class)
        );
        $recommendation = $view->getData()['topSoldItems']->first();

        $this->assertNotNull($recommendation);
        $this->assertSame(10, $view->getData()['historyCoverageDays']);
        $this->assertSame(100, (int) $recommendation->estimated_sold);
        $this->assertSame(100, (int) $recommendation->current_target_quantity);
        $this->assertSame(175, (int) $recommendation->recommended_quantity);
        $this->assertSame(100, (int) $recommendation->recommendation_estimated_sold);
        $this->assertSame(10, (int) $recommendation->recommendation_sales_days_with_data);
        $this->assertSame(10.0, (float) $recommendation->recommendation_daily_sold);
        $this->assertSame(14, (int) $recommendation->recommendation_sales_window);
        $this->assertSame(1.25, (float) $recommendation->recommendation_buffer_multiplier);
        $this->assertSame(175, (int) $recommendation->recommendation_sales_target);
        $this->assertFalse((bool) $recommendation->recommendation_existing_target_covers);
        $this->assertStringContainsString('100 sold / 10 days * 14 sales days * 1.25x buffer = 175', $recommendation->recommendation_reason);
        $this->assertStringContainsString('Low or empty stock events', $recommendation->recommendation_reason);

        $request = Request::create('/market-seeding/history', 'GET', ['days' => 7]);
        app()->instance('request', $request);

        $view = app(MarketSeedingController::class)->history(
            $request,
            app(MarketSeedingSettings::class)
        );
        $recommendation = $view->getData()['topSoldItems']->first();

        $this->assertNotNull($recommendation);
        $this->assertSame(7, $view->getData()['days']);
        $this->assertSame(175, (int) $recommendation->recommended_quantity);
        $this->assertStringContainsString('100 sold / 10 days * 14 sales days * 1.25x buffer = 175', $recommendation->recommendation_reason);
    }

    public function test_history_recommendations_use_each_items_own_days_with_data(): void
    {
        $market = $this->createMarket();
        $olderItem = SeededMarketItem::create([
            'market_id' => $market->id,
            'type_id' => 1425,
            'type_name' => 'Older Tracked Item',
            'desired_quantity' => 1000,
            'warning_quantity' => 333,
        ]);
        $newerItem = SeededMarketItem::create([
            'market_id' => $market->id,
            'type_id' => 1426,
            'type_name' => 'Newly Tracked Item',
            'desired_quantity' => 100,
            'warning_quantity' => 33,
        ]);

        MarketStockDailySummary::create([
            'summary_date' => now()->subDays(13)->toDateString(),
            'market_id' => $market->id,
            'item_id' => $olderItem->id,
            'type_id' => $olderItem->type_id,
            'market_name' => $market->name,
            'location_name' => $market->location_name,
            'type_name' => $olderItem->type_name,
            'type_category' => 'Modules',
            'estimated_sold_quantity' => 0,
            'latest_current_quantity' => 1000,
            'latest_desired_quantity' => 1000,
            'latest_warning_quantity' => 333,
        ]);

        MarketStockDailySummary::create([
            'summary_date' => now()->subDays(6)->toDateString(),
            'market_id' => $market->id,
            'item_id' => $newerItem->id,
            'type_id' => $newerItem->type_id,
            'market_name' => $market->name,
            'location_name' => $market->location_name,
            'type_name' => $newerItem->type_name,
            'type_category' => 'Modules',
            'estimated_sold_quantity' => 35,
            'sales_events' => 1,
            'latest_current_quantity' => 75,
            'latest_desired_quantity' => 100,
            'latest_warning_quantity' => 33,
        ]);

        MarketStockDailySummary::create([
            'summary_date' => now()->toDateString(),
            'market_id' => $market->id,
            'item_id' => $newerItem->id,
            'type_id' => $newerItem->type_id,
            'market_name' => $market->name,
            'location_name' => $market->location_name,
            'type_name' => $newerItem->type_name,
            'type_category' => 'Modules',
            'estimated_sold_quantity' => 35,
            'sales_events' => 1,
            'latest_current_quantity' => 50,
            'latest_desired_quantity' => 100,
            'latest_warning_quantity' => 33,
        ]);

        $request = Request::create('/market-seeding/history', 'GET', ['days' => 365]);
        app()->instance('request', $request);

        $view = app(MarketSeedingController::class)->history(
            $request,
            app(MarketSeedingSettings::class)
        );
        $recommendation = $view->getData()['topSoldItems']->first();

        $this->assertNotNull($recommendation);
        $this->assertSame(14, $view->getData()['historyCoverageDays']);
        $this->assertSame($newerItem->id, (int) $recommendation->item_id);
        $this->assertSame(70, (int) $recommendation->recommendation_estimated_sold);
        $this->assertSame(7, (int) $recommendation->recommendation_sales_days_with_data);
        $this->assertSame(10.0, (float) $recommendation->recommendation_daily_sold);
        $this->assertSame(175, (int) $recommendation->recommended_quantity);
        $this->assertStringContainsString('70 sold / 7 days * 14 sales days * 1.25x buffer = 175', $recommendation->recommendation_reason);
    }

    public function test_history_recommendations_require_seven_days_of_item_history(): void
    {
        $market = $this->createMarket();
        $item = SeededMarketItem::create([
            'market_id' => $market->id,
            'type_id' => 1427,
            'type_name' => 'New Fast Seller',
            'desired_quantity' => 100,
            'warning_quantity' => 33,
        ]);

        MarketStockDailySummary::create([
            'summary_date' => now()->subDays(5)->toDateString(),
            'market_id' => $market->id,
            'item_id' => $item->id,
            'type_id' => $item->type_id,
            'market_name' => $market->name,
            'location_name' => $market->location_name,
            'type_name' => $item->type_name,
            'type_category' => 'Modules',
            'estimated_sold_quantity' => 60,
            'sales_events' => 1,
            'latest_current_quantity' => 40,
            'latest_desired_quantity' => 100,
            'latest_warning_quantity' => 33,
        ]);

        MarketStockDailySummary::create([
            'summary_date' => now()->toDateString(),
            'market_id' => $market->id,
            'item_id' => $item->id,
            'type_id' => $item->type_id,
            'market_name' => $market->name,
            'location_name' => $market->location_name,
            'type_name' => $item->type_name,
            'type_category' => 'Modules',
            'estimated_sold_quantity' => 60,
            'sales_events' => 1,
            'latest_current_quantity' => 20,
            'latest_desired_quantity' => 100,
            'latest_warning_quantity' => 33,
        ]);

        $request = Request::create('/market-seeding/history', 'GET', ['days' => 365]);
        app()->instance('request', $request);

        $view = app(MarketSeedingController::class)->history(
            $request,
            app(MarketSeedingSettings::class)
        );

        $this->assertSame(6, $view->getData()['historyCoverageDays']);
        $this->assertArrayNotHasKey('attentionItems', $view->getData());
        $topSoldItem = $view->getData()['topSoldItems']->first();
        $this->assertNotNull($topSoldItem);
        $this->assertFalse((bool) $topSoldItem->recommendation_differs);
        $this->assertSame('insufficient_data', $topSoldItem->recommendation_driver);
    }

    public function test_history_recommendation_marks_existing_target_as_covering_sales_target(): void
    {
        $market = $this->createMarket();
        $item = SeededMarketItem::create([
            'market_id' => $market->id,
            'type_id' => 1424,
            'type_name' => 'Small Warhead Calefaction Catalyst II',
            'desired_quantity' => 200,
            'warning_quantity' => 66,
        ]);

        MarketStockDailySummary::create([
            'summary_date' => now()->subDays(9)->toDateString(),
            'market_id' => $market->id,
            'item_id' => $item->id,
            'type_id' => $item->type_id,
            'market_name' => $market->name,
            'location_name' => $market->location_name,
            'type_name' => $item->type_name,
            'type_category' => 'Modules',
            'estimated_sold_quantity' => 50,
            'sales_events' => 1,
            'latest_current_quantity' => 150,
            'latest_desired_quantity' => 200,
            'latest_warning_quantity' => 66,
        ]);

        MarketStockDailySummary::create([
            'summary_date' => now()->toDateString(),
            'market_id' => $market->id,
            'item_id' => $item->id,
            'type_id' => $item->type_id,
            'market_name' => $market->name,
            'location_name' => $market->location_name,
            'type_name' => $item->type_name,
            'type_category' => 'Modules',
            'estimated_sold_quantity' => 50,
            'sales_events' => 1,
            'latest_current_quantity' => 150,
            'latest_desired_quantity' => 200,
            'latest_warning_quantity' => 66,
        ]);

        $request = Request::create('/market-seeding/history', 'GET', ['days' => 365]);
        app()->instance('request', $request);

        $view = app(MarketSeedingController::class)->history(
            $request,
            app(MarketSeedingSettings::class)
        );
        $topSoldItem = $view->getData()['topSoldItems']->first();

        $this->assertNotNull($topSoldItem);
        $this->assertSame(175, (int) $topSoldItem->recommendation_sales_target);
        $this->assertSame(200, (int) $topSoldItem->recommended_quantity);
        $this->assertTrue((bool) $topSoldItem->recommendation_existing_target_covers);
        $this->assertFalse((bool) $topSoldItem->recommendation_differs);
    }

    public function test_settings_recommendation_payload_includes_latest_current_stock(): void
    {
        $market = $this->createMarket();
        $item = SeededMarketItem::create([
            'market_id' => $market->id,
            'type_id' => 3244,
            'type_name' => 'Warp Scrambler II',
            'desired_quantity' => 10,
            'warning_quantity' => 3,
        ]);

        foreach (range(6, 0) as $daysAgo) {
            MarketStockDailySummary::create([
                'summary_date' => now()->subDays($daysAgo)->toDateString(),
                'market_id' => $market->id,
                'item_id' => $item->id,
                'type_id' => $item->type_id,
                'market_name' => $market->name,
                'location_name' => $market->location_name,
                'type_name' => $item->type_name,
                'type_category' => 'Modules',
                'estimated_sold_quantity' => 10,
                'sales_events' => 1,
                'latest_current_quantity' => $daysAgo === 0 ? 4 : 9,
                'latest_desired_quantity' => 10,
                'latest_warning_quantity' => 3,
            ]);
        }

        $service = app(MarketTargetRecommendations::class);
        $recommendations = $service->forMarkets(
            collect([$market]),
            14,
            25,
            app(MarketStockReport::class)
        );
        $payload = $service->payload($recommendations[$market->id]->first());

        $this->assertSame(4, $payload['current_stock_quantity']);
        $this->assertSame(175, $payload['recommended_quantity']);
    }

    public function test_apply_recommendations_returns_updated_item_payloads(): void
    {
        $market = $this->createMarket();
        $item = SeededMarketItem::create([
            'market_id' => $market->id,
            'type_id' => 3244,
            'type_name' => 'Warp Scrambler II',
            'desired_quantity' => 10,
            'warning_quantity' => 3,
        ]);

        foreach (range(6, 0) as $daysAgo) {
            MarketStockDailySummary::create([
                'summary_date' => now()->subDays($daysAgo)->toDateString(),
                'market_id' => $market->id,
                'item_id' => $item->id,
                'type_id' => $item->type_id,
                'market_name' => $market->name,
                'location_name' => $market->location_name,
                'type_name' => $item->type_name,
                'type_category' => 'Modules',
                'estimated_sold_quantity' => 10,
                'sales_events' => 1,
                'latest_current_quantity' => 4,
                'latest_desired_quantity' => 10,
                'latest_warning_quantity' => 3,
            ]);
        }

        $request = Request::create('/market-seeding/settings/recommendations/apply', 'POST', [
            'item_ids' => [$item->id],
        ]);
        app()->instance('request', $request);

        $response = app(MarketSeedingController::class)->applyRecommendations(
            $request,
            app(MarketSeedingSettings::class),
            app(StockTargetProjector::class)
        );
        $payload = $response->getData(true);

        $this->assertSame(1, $payload['updated']);
        $this->assertSame($item->id, $payload['items'][0]['id']);
        $this->assertSame(175, $payload['items'][0]['desired_quantity']);
        $this->assertSame(53, $payload['items'][0]['warning_quantity']);
    }

    public function test_item_history_includes_source_details(): void
    {
        $market = $this->createMarket();
        $item = SeededMarketItem::create([
            'market_id' => $market->id,
            'type_id' => 2048,
            'type_name' => 'Damage Control II',
            'desired_quantity' => 25,
            'warning_quantity' => 8,
        ]);

        MarketSeedingItemSource::create([
            'market_id' => $market->id,
            'item_id' => $item->id,
            'source_type' => MarketSeedingItemSource::SOURCE_MANUAL,
            'source_key' => 'manual',
            'type_id' => $item->type_id,
            'type_name' => $item->type_name,
            'quantity' => 25,
            'warning_quantity' => 8,
        ]);

        $response = app(MarketSeedingController::class)->itemHistory(
            Request::create('/market-seeding/items/' . $item->id . '/history', 'GET'),
            $item,
            app(MarketStockReport::class)
        );
        $payload = $response->getData(true);

        $this->assertSame(2048, $payload['item']['type_id']);
        $this->assertTrue($payload['source_details']['flags']['manual']);
        $this->assertFalse($payload['source_details']['flags']['doctrine']);
        $this->assertSame('Manual add', $payload['source_details']['manual'][0]['label']);
        $this->assertSame(25, $payload['source_details']['manual'][0]['quantity']);
    }

    public function test_item_history_returns_active_character_sell_orders_for_seeders(): void
    {
        $market = $this->createMarket(['location_id' => 60000001]);
        $item = SeededMarketItem::create([
            'market_id' => $market->id,
            'type_id' => 2048,
            'type_name' => 'Damage Control II',
            'desired_quantity' => 25,
            'warning_quantity' => 8,
        ]);

        auth()->user()->update(['main_character_id' => 90000002]);
        DB::table('character_infos')->insert([
            [
                'character_id' => 90000001,
                'name' => 'Market Alt',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'character_id' => 90000002,
                'name' => 'Main Pilot',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'character_id' => 90000003,
                'name' => 'Higher Price Alt',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        DB::table('refresh_tokens')->insert([
            'character_id' => 90000001,
            'user_id' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('market_orders')->insert([
            'location_id' => MarketStockReport::JITA_STATION_ID,
            'type_id' => 2048,
            'price' => 1200000,
            'volume_remaining' => 50,
            'is_buy_order' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('character_orders')->insert([
            [
                'character_id' => 90000001,
                'order_id' => 70000001,
                'type_id' => 2048,
                'location_id' => 60000001,
                'is_buy_order' => null,
                'price' => 1250000,
                'volume_total' => 10,
                'volume_remain' => 4,
                'issued' => now()->subDays(2),
                'duration' => 7,
                'state' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'character_id' => 90000001,
                'order_id' => 70000002,
                'type_id' => 2048,
                'location_id' => 60000001,
                'is_buy_order' => true,
                'price' => 1000000,
                'volume_total' => 10,
                'volume_remain' => 10,
                'issued' => now()->subDays(1),
                'duration' => 90,
                'state' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'character_id' => 90000001,
                'order_id' => 70000003,
                'type_id' => 2048,
                'location_id' => 60000001,
                'is_buy_order' => false,
                'price' => 1300000,
                'volume_total' => 10,
                'volume_remain' => 10,
                'issued' => now()->subDays(1),
                'duration' => 90,
                'state' => 'cancelled',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'character_id' => 90000003,
                'order_id' => 70000004,
                'type_id' => 2048,
                'location_id' => 60000001,
                'is_buy_order' => false,
                'price' => 1400000,
                'volume_total' => 10,
                'volume_remain' => 9,
                'issued' => now()->subDays(1),
                'duration' => 90,
                'state' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = app(MarketSeedingController::class)->itemHistory(
            Request::create('/market-seeding/items/' . $item->id . '/history', 'GET'),
            $item,
            app(MarketStockReport::class)
        );
        $payload = $response->getData(true);

        $this->assertCount(2, $payload['sell_orders']);
        $this->assertSame('Market Alt', $payload['sell_orders'][0]['character_name']);
        $this->assertSame('https://images.evetech.net/characters/90000001/portrait?size=64', $payload['sell_orders'][0]['character_portrait_url']);
        $this->assertSame(90000002, $payload['sell_orders'][0]['main_character_id']);
        $this->assertSame('Main Pilot', $payload['sell_orders'][0]['main_character_name']);
        $this->assertSame(4, $payload['sell_orders'][0]['quantity_remaining']);
        $this->assertSame(10, $payload['sell_orders'][0]['quantity_total']);
        $this->assertEquals(1250000.0, $payload['sell_orders'][0]['price']);
        $this->assertEquals(1200000.0, $payload['sell_orders'][0]['jita_price']);
        $this->assertEquals(50000.0, $payload['sell_orders'][0]['jita_delta']);
        $this->assertEqualsWithDelta(4.17, $payload['sell_orders'][0]['jita_delta_percent'], 0.01);
        $this->assertSame(5, $payload['sell_orders'][0]['days_until_expiry']);
        $this->assertSame('Higher Price Alt', $payload['sell_orders'][1]['character_name']);
        $this->assertEquals(1400000.0, $payload['sell_orders'][1]['price']);
    }

    public function test_seeders_groups_active_tracked_sell_orders_by_main_character_per_market(): void
    {
        $this->seedSde();
        $this->seedType(2048, 'Damage Control II', ['volume' => 5]);
        $this->seedType(4096, 'ECCM Script', ['volume' => 0.01]);
        $market = $this->createMarket(['location_id' => 60000001, 'name' => 'Home']);
        $otherMarket = $this->createMarket(['location_id' => 60000002, 'name' => 'Forward']);
        $homeItem = SeededMarketItem::create([
            'market_id' => $market->id,
            'type_id' => 2048,
            'type_name' => 'Damage Control II',
            'desired_quantity' => 25,
            'warning_quantity' => 8,
        ]);
        SeededMarketItem::create([
            'market_id' => $otherMarket->id,
            'type_id' => 4096,
            'type_name' => 'ECCM Script',
            'desired_quantity' => 30,
            'warning_quantity' => 10,
        ]);

        auth()->user()->update(['main_character_id' => 90000002]);
        DB::table('users')->insert([
            'id' => 2,
            'name' => 'other',
            'main_character_id' => 90000005,
            'active' => true,
            'admin' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('character_infos')->insert([
            ['character_id' => 90000001, 'name' => 'Market Alt A', 'created_at' => now(), 'updated_at' => now()],
            ['character_id' => 90000002, 'name' => 'Main Pilot', 'created_at' => now(), 'updated_at' => now()],
            ['character_id' => 90000003, 'name' => 'Market Alt B', 'created_at' => now(), 'updated_at' => now()],
            ['character_id' => 90000004, 'name' => 'Other Alt', 'created_at' => now(), 'updated_at' => now()],
            ['character_id' => 90000005, 'name' => 'Other Main', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('refresh_tokens')->insert([
            ['character_id' => 90000001, 'user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()],
            ['character_id' => 90000003, 'user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()],
            ['character_id' => 90000004, 'user_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('character_orders')->insert([
            [
                'character_id' => 90000001,
                'order_id' => 80000001,
                'type_id' => 2048,
                'location_id' => 60000001,
                'is_buy_order' => null,
                'price' => 100,
                'volume_total' => 20,
                'volume_remain' => 10,
                'issued' => now()->subDays(80),
                'duration' => 90,
                'state' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'character_id' => 90000003,
                'order_id' => 80000002,
                'type_id' => 2048,
                'location_id' => 60000001,
                'is_buy_order' => false,
                'price' => 200,
                'volume_total' => 20,
                'volume_remain' => 5,
                'issued' => now(),
                'duration' => 90,
                'state' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'character_id' => 90000004,
                'order_id' => 80000003,
                'type_id' => 2048,
                'location_id' => 60000001,
                'is_buy_order' => false,
                'price' => 50,
                'volume_total' => 20,
                'volume_remain' => 4,
                'issued' => now(),
                'duration' => 90,
                'state' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'character_id' => 90000001,
                'order_id' => 80000004,
                'type_id' => 9999,
                'location_id' => 60000001,
                'is_buy_order' => false,
                'price' => 999999,
                'volume_total' => 1,
                'volume_remain' => 1,
                'issued' => now(),
                'duration' => 90,
                'state' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'character_id' => 90000001,
                'order_id' => 80000005,
                'type_id' => 4096,
                'location_id' => 60000002,
                'is_buy_order' => false,
                'price' => 25,
                'volume_total' => 30,
                'volume_remain' => 21,
                'issued' => now(),
                'duration' => 90,
                'state' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $view = app(MarketSeedingController::class)->seeders(app(MarketStockReport::class));
        $leaderboards = $view->getData()['leaderboards'];
        $homeRows = $leaderboards[$market->id]['rows'];
        $forwardRows = $leaderboards[$otherMarket->id]['rows'];

        $this->assertSame(2, $leaderboards[$market->id]['total_seeders']);
        $this->assertSame(3, $leaderboards[$market->id]['total_orders']);
        $this->assertEquals(2200.0, $leaderboards[$market->id]['total_value']);
        $this->assertEquals(95.0, $leaderboards[$market->id]['total_volume']);
        $this->assertSame('Main Pilot', $homeRows[0]['main_character_name']);
        $this->assertEquals(2000.0, $homeRows[0]['total_value']);
        $this->assertEquals(75.0, $homeRows[0]['total_volume']);
        $this->assertArrayNotHasKey('orders', $homeRows[0]);
        $this->assertSame('user:' . auth()->id(), $homeRows[0]['account_key']);
        $this->assertTrue($homeRows[0]['has_expiring_orders']);
        $this->assertSame(1, $homeRows[0]['expiring_order_count']);
        $this->assertSame(2, $homeRows[0]['character_count']);
        $this->assertSame(['Market Alt A', 'Market Alt B'], $homeRows[0]['characters']);
        $this->assertSame('Other Main', $homeRows[1]['main_character_name']);
        $this->assertFalse($homeRows[1]['has_expiring_orders']);
        $this->assertEquals(200.0, $homeRows[1]['total_value']);
        $this->assertEquals(20.0, $homeRows[1]['total_volume']);
        $this->assertSame(1, $forwardRows->count());
        $this->assertSame('Main Pilot', $forwardRows[0]['main_character_name']);
        $this->assertEquals(525.0, $forwardRows[0]['total_value']);
        $this->assertEquals(0.21, $forwardRows[0]['total_volume']);

        $ordersRequest = Request::create('/market-seeding/seeders/markets/' . $market->id . '/orders', 'GET', [
            'seeder_key' => $homeRows[0]['account_key'],
        ]);
        app()->instance('request', $ordersRequest);
        $ordersPayload = app(MarketSeedingController::class)
            ->seederOrders($ordersRequest, $market, app(MarketStockReport::class))
            ->getData(true);

        $this->assertSame(2, $ordersPayload['order_count']);
        $this->assertEquals(2000.0, $ordersPayload['listed_value']);
        $this->assertEquals(75.0, $ordersPayload['total_volume']);
        $this->assertSame('Damage Control II', $ordersPayload['orders'][0]['item_name']);
        $this->assertSame($homeItem->id, $ordersPayload['orders'][0]['item_id']);
        $this->assertSame(2048, $ordersPayload['orders'][0]['type_id']);
        $this->assertSame(route('market-seeding.items.history', $homeItem), $ordersPayload['orders'][0]['history_url']);
        $this->assertSame('Market Alt A', $ordersPayload['orders'][0]['character_name']);
        $this->assertSame(10, $ordersPayload['orders'][0]['quantity_remaining']);
        $this->assertEquals(50.0, $ordersPayload['orders'][0]['total_volume']);
        $this->assertTrue($ordersPayload['orders'][0]['expires_soon']);
    }

    public function test_expiring_order_warning_only_includes_current_users_tracked_sell_orders_expiring_soon(): void
    {
        $market = $this->createMarket(['location_id' => 60000001, 'name' => 'Home']);
        SeededMarketItem::create([
            'market_id' => $market->id,
            'type_id' => 2048,
            'type_name' => 'Damage Control II',
            'desired_quantity' => 25,
            'warning_quantity' => 8,
        ]);

        DB::table('character_infos')->insert([
            ['character_id' => 90000001, 'name' => 'My Market Alt', 'created_at' => now(), 'updated_at' => now()],
            ['character_id' => 90000002, 'name' => 'Other Pilot', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('refresh_tokens')->insert([
            ['character_id' => 90000001, 'user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()],
            ['character_id' => 90000002, 'user_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('users')->insert([
            'id' => 2,
            'name' => 'other',
            'active' => true,
            'admin' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('character_orders')->insert([
            [
                'character_id' => 90000001,
                'order_id' => 81000001,
                'type_id' => 2048,
                'location_id' => 60000001,
                'is_buy_order' => false,
                'price' => 1200000,
                'volume_total' => 10,
                'volume_remain' => 4,
                'issued' => now()->subDays(84),
                'duration' => 90,
                'state' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'character_id' => 90000001,
                'order_id' => 81000002,
                'type_id' => 2048,
                'location_id' => 60000001,
                'is_buy_order' => false,
                'price' => 1100000,
                'volume_total' => 10,
                'volume_remain' => 4,
                'issued' => now()->subDays(30),
                'duration' => 90,
                'state' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'character_id' => 90000001,
                'order_id' => 81000003,
                'type_id' => 9999,
                'location_id' => 60000001,
                'is_buy_order' => false,
                'price' => 999,
                'volume_total' => 1,
                'volume_remain' => 1,
                'issued' => now()->subDays(84),
                'duration' => 90,
                'state' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'character_id' => 90000002,
                'order_id' => 81000004,
                'type_id' => 2048,
                'location_id' => 60000001,
                'is_buy_order' => false,
                'price' => 1200000,
                'volume_total' => 10,
                'volume_remain' => 4,
                'issued' => now()->subDays(84),
                'duration' => 90,
                'state' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $warnings = app(MarketOrderExpiryWarnings::class)->forUser(auth()->user(), 7);

        $this->assertSame(1, $warnings['count']);
        $this->assertEquals(4800000.0, $warnings['total_value']);
        $this->assertSame('My Market Alt', $warnings['orders'][0]['character_name']);
        $this->assertSame('Damage Control II', $warnings['orders'][0]['type_name']);
        $this->assertSame('Home', $warnings['orders'][0]['market_name']);
        $this->assertSame(4, $warnings['orders'][0]['quantity_remaining']);
        $this->assertSame(6, (int) $warnings['orders'][0]['days_until_expiry']);
    }

    public function test_listing_helper_prices_resolve_local_and_jita_prices(): void
    {
        $this->seedSde();
        $this->seedType(2048, 'Damage Control II');
        $this->seedType(1234, 'Known Without Local');
        $market = $this->createMarket(['location_id' => 60000001]);

        DB::table('market_orders')->insert([
            [
                'location_id' => 60000001,
                'type_id' => 2048,
                'volume_remaining' => 5,
                'price' => 1500000,
                'is_buy_order' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'location_id' => MarketStockReport::JITA_STATION_ID,
                'type_id' => 2048,
                'volume_remaining' => 5,
                'price' => 1200000,
                'is_buy_order' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        DB::table('market_prices')->insert([
            'type_id' => 1234,
            'average_price' => 750000,
            'sell_price' => 800000,
            'adjusted_price' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = app(MarketSeedingController::class)->listingHelperPrices(
            Request::create('/market-seeding/markets/' . $market->id . '/listing-helper/prices', 'POST', [
                'items' => ['Damage Control II', 'Known Without Local', 'Unknown Thing'],
            ]),
            $market
        );
        $payload = $response->getData(true);

        $this->assertTrue($payload['prices']['Damage Control II']['found']);
        $this->assertEquals(1500000.0, $payload['prices']['Damage Control II']['local_price']);
        $this->assertEquals(1200000.0, $payload['prices']['Damage Control II']['jita_price']);
        $this->assertTrue($payload['prices']['Known Without Local']['found']);
        $this->assertNull($payload['prices']['Known Without Local']['local_price']);
        $this->assertEquals(800000.0, $payload['prices']['Known Without Local']['jita_price']);
        $this->assertFalse($payload['prices']['Unknown Thing']['found']);
    }
}
