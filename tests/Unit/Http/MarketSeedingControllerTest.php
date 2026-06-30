<?php

namespace Raikia\SeatMarketSeeding\Tests\Unit\Http;

use Illuminate\Http\Request;
use Raikia\SeatMarketSeeding\Http\Controllers\MarketSeedingController;
use Raikia\SeatMarketSeeding\Models\MarketStockDailySummary;
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
}
