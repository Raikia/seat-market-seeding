<?php

namespace Raikia\SeatMarketSeeding\Tests\Unit\Http;

use Illuminate\Support\Facades\Queue;
use Raikia\SeatMarketSeeding\Http\Controllers\SettingsController;
use Raikia\SeatMarketSeeding\Jobs\RefreshMarketSeedingMarkets;
use Raikia\SeatMarketSeeding\Models\MarketSeedingTargetHistory;
use Raikia\SeatMarketSeeding\Models\MarketSeedingTrackedDoctrine;
use Raikia\SeatMarketSeeding\Models\MarketStockDailySummary;
use Raikia\SeatMarketSeeding\Models\MarketStockHistory;
use Raikia\SeatMarketSeeding\Models\MarketStockSnapshot;
use Raikia\SeatMarketSeeding\Services\DoctrineTrackingSync;
use Raikia\SeatMarketSeeding\Services\SavedFittingSource;
use Raikia\SeatMarketSeeding\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

    public function test_seat_fitting_searches_are_empty_when_plugin_is_not_installed(): void
    {
        $source = app(SavedFittingSource::class);

        $this->assertFalse(app(DoctrineTrackingSync::class)->isAvailable());
        $this->assertSame([], $source->searchDoctrines('caracal'));
        $this->assertSame([], $source->items('seat-fitting-doctrine', 1, 10));
        $this->assertSame([], $source->items('seat-fitting-fit', 1, 10));
    }

    public function test_doctrine_sync_noops_when_seat_fitting_is_not_installed(): void
    {
        $market = $this->createMarket();
        $trackedDoctrine = MarketSeedingTrackedDoctrine::create([
            'market_id' => $market->id,
            'doctrine_id' => 123,
            'doctrine_name' => 'Attackers',
            'multiplier' => 10,
            'warning_percentage' => 33,
            'merge_mode' => MarketSeedingTrackedDoctrine::MERGE_MAX,
            'fit_aggregation_mode' => MarketSeedingTrackedDoctrine::FIT_AGGREGATION_MAX,
        ]);

        $this->assertSame(0, app(DoctrineTrackingSync::class)->syncMarket($market));
        app(DoctrineTrackingSync::class)->syncDoctrine($trackedDoctrine);

        $trackedDoctrine->refresh();
        $this->assertSame('skipped', $trackedDoctrine->last_sync_status);
        $this->assertSame('Seat Fitting is not installed.', $trackedDoctrine->last_sync_message);
    }

    public function test_updating_tracked_doctrine_returns_not_found_when_seat_fitting_is_not_installed(): void
    {
        $market = $this->createMarket();
        $trackedDoctrine = MarketSeedingTrackedDoctrine::create([
            'market_id' => $market->id,
            'doctrine_id' => 123,
            'doctrine_name' => 'Attackers',
            'multiplier' => 10,
            'warning_percentage' => 33,
            'merge_mode' => MarketSeedingTrackedDoctrine::MERGE_MAX,
            'fit_aggregation_mode' => MarketSeedingTrackedDoctrine::FIT_AGGREGATION_MAX,
        ]);

        try {
            app(SettingsController::class)->updateTrackedDoctrine(
                request()->create('/market-seeding/tracked-doctrines/' . $trackedDoctrine->id, 'PUT', [
                    'multiplier' => 20,
                    'warning_percentage' => 20,
                    'merge_mode' => MarketSeedingTrackedDoctrine::MERGE_ADD,
                    'fit_aggregation_mode' => MarketSeedingTrackedDoctrine::FIT_AGGREGATION_SUM,
                ]),
                $trackedDoctrine,
                app(DoctrineTrackingSync::class)
            );

            $this->fail('Expected tracked doctrine update to abort when Seat Fitting is unavailable.');
        } catch (NotFoundHttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }

        $trackedDoctrine->refresh();
        $this->assertSame(10, (int) $trackedDoctrine->multiplier);
        $this->assertSame(33, (int) $trackedDoctrine->warning_percentage);
        $this->assertSame(MarketSeedingTrackedDoctrine::MERGE_MAX, $trackedDoctrine->merge_mode);
        $this->assertSame(MarketSeedingTrackedDoctrine::FIT_AGGREGATION_MAX, $trackedDoctrine->fit_aggregation_mode);
    }
}
