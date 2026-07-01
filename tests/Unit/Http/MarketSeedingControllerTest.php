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
use Raikia\SeatMarketSeeding\Services\MarketStockReport;
use Raikia\SeatMarketSeeding\Tests\TestCase;

class MarketSeedingControllerTest extends TestCase
{
    public function test_history_defaults_to_ninety_days(): void
    {
        $request = Request::create('/market-seeding/history', 'GET');
        app()->instance('request', $request);

        $view = app(MarketSeedingController::class)->history(
            $request,
            app(MarketSeedingSettings::class),
            app(MarketStockReport::class)
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

        $request = Request::create('/market-seeding/history', 'GET', ['days' => 365]);
        app()->instance('request', $request);

        $view = app(MarketSeedingController::class)->history(
            $request,
            app(MarketSeedingSettings::class),
            app(MarketStockReport::class)
        );

        $this->assertSame(365, $view->getData()['days']);
        $this->assertSame(10, $view->getData()['historyCoverageDays']);
        $this->assertSame(10.0, $view->getData()['salesSummary']['average_daily_sold']);
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
            app(MarketSeedingSettings::class),
            app(MarketStockReport::class)
        );

        $this->assertTrue($view->getData()['attentionItems']->isEmpty());
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
            app(MarketSeedingSettings::class),
            app(MarketStockReport::class)
        );
        $recommendation = $view->getData()['attentionItems']->first();

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
            app(MarketSeedingSettings::class),
            app(MarketStockReport::class)
        );
        $recommendation = $view->getData()['attentionItems']->first();

        $this->assertNotNull($recommendation);
        $this->assertSame(7, $view->getData()['days']);
        $this->assertSame(175, (int) $recommendation->recommended_quantity);
        $this->assertStringContainsString('100 sold / 10 days * 14 sales days * 1.25x buffer = 175', $recommendation->recommendation_reason);
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
            app(MarketSeedingSettings::class),
            app(MarketStockReport::class)
        );
        $topSoldItem = $view->getData()['topSoldItems']->first();

        $this->assertNotNull($topSoldItem);
        $this->assertSame(175, (int) $topSoldItem->recommendation_sales_target);
        $this->assertSame(200, (int) $topSoldItem->recommended_quantity);
        $this->assertTrue((bool) $topSoldItem->recommendation_existing_target_covers);
        $this->assertFalse((bool) $topSoldItem->recommendation_differs);
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
