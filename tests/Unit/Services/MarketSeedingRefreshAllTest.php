<?php

namespace Raikia\SeatMarketSeeding\Tests\Unit\Services;

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
        });
        app()->instance(MarketStockTransitionNotifier::class, new class extends MarketStockTransitionNotifier {
            public function __construct()
            {
            }

            public function checkMarket(SeededMarket $market): int
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
}
