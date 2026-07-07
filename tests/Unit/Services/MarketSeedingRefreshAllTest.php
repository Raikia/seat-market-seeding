<?php

namespace Raikia\SeatMarketSeeding\Tests\Unit\Services;

use Illuminate\Support\Facades\DB;
use Raikia\SeatMarketSeeding\Models\SeededMarket;
use Raikia\SeatMarketSeeding\Services\DoctrineTrackingSync;
use Raikia\SeatMarketSeeding\Services\EsiMarketOrderRefresh;
use Raikia\SeatMarketSeeding\Services\MarketSeedingRefreshAll;
use Raikia\SeatMarketSeeding\Services\MarketStockTransitionNotifier;
use Raikia\SeatMarketSeeding\Support\MarketSeedingCache;
use Raikia\SeatMarketSeeding\Tests\TestCase;
use Seat\Eveapi\Models\RefreshToken;

class MarketSeedingRefreshAllTest extends TestCase
{
    public function test_refresh_tracks_expired_stale_quantities_before_deleting_orders(): void
    {
        DB::table('market_orders')->insert([
            [
                'order_id' => 1001,
                'location_id' => 60000001,
                'type_id' => 123,
                'volume_remaining' => 5,
                'volume_total' => 10,
                'price' => 1000,
                'is_buy_order' => false,
                'issued' => now()->subDays(91),
                'expiry' => now()->subDay(),
                'duration' => 90,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => 1002,
                'location_id' => 60000001,
                'type_id' => 123,
                'volume_remaining' => 3,
                'volume_total' => 10,
                'price' => 1000,
                'is_buy_order' => false,
                'issued' => now()->subDay(),
                'expiry' => now()->addDays(89),
                'duration' => 90,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $refresh = app(EsiMarketOrderRefresh::class);
        $method = new \ReflectionMethod(EsiMarketOrderRefresh::class, 'deleteStaleSellOrders');
        $method->setAccessible(true);
        $result = $method->invoke($refresh, 60000001, [123], []);

        $this->assertSame(2, $result['deleted']);
        $this->assertSame(5, $result['expired_quantity']);
        $this->assertSame(['60000001:123' => 5], $refresh->expiredStaleQuantities());
        $this->assertSame(0, DB::table('market_orders')->count());
    }

    public function test_refresh_bumps_history_price_cache_version_once_per_run(): void
    {
        $this->createMarket([
            'name' => 'Home',
            'is_structure' => false,
        ]);
        $this->createMarket([
            'name' => 'Forward',
            'location_id' => 60000002,
            'is_structure' => false,
        ]);

        app()->instance(EsiMarketOrderRefresh::class, new class extends EsiMarketOrderRefresh {
            public function __construct()
            {
            }

            public function refresh(SeededMarket $market, ?RefreshToken $refreshToken = null): int
            {
                return 0;
            }

            public function getLastStats(): array
            {
                return [];
            }

            public function expiredStaleQuantities(): array
            {
                return [];
            }
        });
        app()->instance(MarketStockTransitionNotifier::class, new class extends MarketStockTransitionNotifier {
            public function __construct()
            {
            }

            public function checkMarket(SeededMarket $market, array $expiredStaleQuantities = []): int
            {
                return 0;
            }
        });
        app()->instance(DoctrineTrackingSync::class, new class extends DoctrineTrackingSync {
            public function __construct()
            {
            }

            public function syncMarket(SeededMarket $market): int
            {
                return 0;
            }
        });

        $before = MarketSeedingCache::historyPriceVersion();
        $results = app(MarketSeedingRefreshAll::class)->refresh();

        $this->assertSame(2, $results['markets']);
        $this->assertSame($before + 1, MarketSeedingCache::historyPriceVersion());
    }

    public function test_refresh_records_actionable_message_for_authorization_failures(): void
    {
        $market = $this->createMarket([
            'name' => 'Home',
            'is_structure' => false,
        ]);

        app()->instance(EsiMarketOrderRefresh::class, new class extends EsiMarketOrderRefresh {
            public function __construct()
            {
            }

            public function refresh(SeededMarket $market, ?RefreshToken $refreshToken = null): int
            {
                throw new \RuntimeException('Not authorized', 403);
            }

            public function getLastStats(): array
            {
                return [];
            }

            public function expiredStaleQuantities(): array
            {
                return [];
            }
        });
        app()->instance(MarketStockTransitionNotifier::class, new class extends MarketStockTransitionNotifier {
            public function __construct()
            {
            }

            public function checkMarket(SeededMarket $market, array $expiredStaleQuantities = []): int
            {
                return 0;
            }
        });
        app()->instance(DoctrineTrackingSync::class, new class extends DoctrineTrackingSync {
            public function __construct()
            {
            }

            public function syncMarket(SeededMarket $market): int
            {
                return 0;
            }
        });

        $results = app(MarketSeedingRefreshAll::class)->refresh();

        $this->assertSame(0, $results['markets']);
        $this->assertSame(['Home: ESI denied access to this market endpoint.'], $results['errors']);
        $this->assertSame('error', $market->fresh()->last_refresh_status);
        $this->assertSame('ESI denied access to this market endpoint.', $market->fresh()->last_refresh_message);
    }
}
