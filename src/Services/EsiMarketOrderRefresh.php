<?php

namespace Raikia\SeatMarketSeeding\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Raikia\SeatMarketSeeding\Models\SeededMarket;
use Seat\Eveapi\Models\Market\MarketOrder;
use Seat\Eveapi\Models\RefreshToken;
use Seat\Eveapi\Services\EseyeClient;

class EsiMarketOrderRefresh
{
    const THE_FORGE_REGION_ID = 10000002;
    const JITA_STATION_ID = 60003760;

    private array $refreshedLocationTypes = [];
    private array $lastStats = [];
    private MarketSeedingSettings $settings;

    public function __construct(MarketSeedingSettings $settings)
    {
        $this->settings = $settings;
    }

    public function refresh(SeededMarket $market, ?RefreshToken $refreshToken = null): int
    {
        $this->resetStats();
        $market->load('items');

        if ($market->items->isEmpty()) {
            return 0;
        }

        if ($market->is_structure) {
            if (!$refreshToken) {
                throw new \RuntimeException('Refreshing structure markets requires a main character token with structure market access.');
            }

            return $this->refreshStructureMarket($market, $refreshToken) + $this->refreshJitaOrders($market);
        }

        return $this->refreshStationMarket($market) + $this->refreshJitaOrders($market);
    }

    public function getLastStats(): array
    {
        return $this->lastStats;
    }

    private function refreshStationMarket(SeededMarket $market): int
    {
        return $this->refreshRegionLocationTypeOrders(
            $market->region_id,
            $market->location_id,
            $market->items->pluck('type_id')->all(),
            0,
            'market'
        );
    }

    private function refreshJitaOrders(SeededMarket $market): int
    {
        return $this->refreshRegionLocationTypeOrders(
            self::THE_FORGE_REGION_ID,
            self::JITA_STATION_ID,
            $market->items->pluck('type_id')->all(),
            $this->settings->jitaPriceRefreshMinutes(),
            'jita'
        );
    }

    private function refreshRegionLocationTypeOrders(int $regionId, int $locationId, array $typeIds, int $cacheMinutes = 0, string $segment = 'market'): int
    {
        $client = new EseyeClient();
        $count = 0;
        $uniqueTypeIds = collect($typeIds)->map(fn ($typeId) => (int) $typeId)->unique()->values();

        $this->incrementStat($segment . '_types_seen', $uniqueTypeIds->count());
        $this->lastStats[$segment . '_cache_minutes'] = $cacheMinutes;

        foreach ($uniqueTypeIds as $typeId) {
            $cacheHit = $this->refreshedLocationTypeHit($locationId, $typeId, $cacheMinutes);

            if ($cacheHit) {
                $this->incrementStat($segment . '_types_cached');
                $this->incrementStat($segment . '_cache_' . $cacheHit . '_hits');
                continue;
            }

            $this->incrementStat($segment . '_types_refreshed');
            $page = 1;
            $refreshedOrderIds = [];

            do {
                $this->incrementStat($segment . '_esi_requests');
                $response = $client->setQueryString([
                    'order_type' => 'sell',
                    'type_id' => $typeId,
                    'page' => $page,
                ])->invoke('get', '/markets/{region_id}/orders/', [
                    'region_id' => $regionId,
                ]);

                $orders = collect($response->getBody())
                    ->where('location_id', $locationId)
                    ->values();

                $count += $this->upsertOrders($orders);
                $this->incrementStat($segment . '_pages');
                $this->incrementStat($segment . '_orders_seen', $orders->count());
                $refreshedOrderIds = array_merge($refreshedOrderIds, $orders->pluck('order_id')->map(fn ($orderId) => (int) $orderId)->all());

                $pages = $response->getPagesCount() ?: 1;
                $page++;
            } while ($page <= $pages);

            $this->incrementStat($segment . '_stale_deleted', $this->deleteStaleSellOrders($locationId, [$typeId], $refreshedOrderIds));
            $this->markLocationTypeRefreshed($locationId, $typeId, $cacheMinutes);
        }

        return $count;
    }

    private function refreshStructureMarket(SeededMarket $market, RefreshToken $refreshToken): int
    {
        $client = new EseyeClient();
        $client->setAuthentication($refreshToken);

        $trackedTypeIds = $market->items->pluck('type_id')->map(function ($typeId) {
            return (int) $typeId;
        })->all();

        $count = 0;
        $page = 1;
        $refreshedOrderIds = [];

        $this->incrementStat('market_types_seen', count(array_unique($trackedTypeIds)));
        $this->incrementStat('market_types_refreshed', count(array_unique($trackedTypeIds)));

        do {
            $this->incrementStat('market_esi_requests');
            $response = $client->setQueryString([
                'page' => $page,
            ])->invoke('get', '/markets/structures/{structure_id}/', [
                'structure_id' => $market->location_id,
            ]);

            $orders = collect($response->getBody())
                ->where('is_buy_order', false)
                ->whereIn('type_id', $trackedTypeIds)
                ->map(function ($order) use ($market) {
                    $order->location_id = $market->location_id;
                    $order->system_id = $order->system_id ?? $market->solar_system_id ?? 0;

                    return $order;
                })
                ->values();

            $count += $this->upsertOrders($orders);
            $this->incrementStat('market_pages');
            $this->incrementStat('market_orders_seen', $orders->count());
            $refreshedOrderIds = array_merge($refreshedOrderIds, $orders->pluck('order_id')->map(fn ($orderId) => (int) $orderId)->all());

            $pages = $response->getPagesCount() ?: 1;
            $page++;
        } while ($page <= $pages);

        $this->incrementStat('market_stale_deleted', $this->deleteStaleSellOrders($market->location_id, $trackedTypeIds, $refreshedOrderIds));

        return $count;
    }

    private function deleteStaleSellOrders(int $locationId, array $typeIds, array $refreshedOrderIds): int
    {
        $query = MarketOrder::query()
            ->where('location_id', $locationId)
            ->whereIn('type_id', $typeIds)
            ->where('is_buy_order', false);

        if (!empty($refreshedOrderIds)) {
            $query->whereNotIn('order_id', array_unique($refreshedOrderIds));
        }

        return $query->delete();
    }

    private function refreshedLocationTypeHit(int $locationId, int $typeId, int $cacheMinutes = 0): ?string
    {
        $key = $this->locationTypeKey($locationId, $typeId);

        if (array_key_exists($key, $this->refreshedLocationTypes)) {
            return 'memory';
        }

        if ($cacheMinutes <= 0) {
            return null;
        }

        if (Cache::has($this->locationTypeCacheKey($key))) {
            return 'store';
        }

        if ($this->hasRecentlyRefreshedOrders($locationId, $typeId, $cacheMinutes)) {
            return 'database';
        }

        return null;
    }

    private function markLocationTypeRefreshed(int $locationId, int $typeId, int $cacheMinutes = 0): void
    {
        $key = $this->locationTypeKey($locationId, $typeId);
        $this->refreshedLocationTypes[$key] = true;

        if ($cacheMinutes > 0) {
            Cache::put($this->locationTypeCacheKey($key), true, now()->addMinutes($cacheMinutes));
        }
    }

    private function locationTypeKey(int $locationId, int $typeId): string
    {
        return sprintf('%d:%d', $locationId, $typeId);
    }

    private function locationTypeCacheKey(string $locationTypeKey): string
    {
        return 'seat-market-seeding:market-orders-refreshed:' . $locationTypeKey;
    }

    private function hasRecentlyRefreshedOrders(int $locationId, int $typeId, int $cacheMinutes): bool
    {
        return MarketOrder::query()
            ->where('location_id', $locationId)
            ->where('type_id', $typeId)
            ->where('is_buy_order', false)
            ->where('updated_at', '>=', now()->subMinutes($cacheMinutes))
            ->exists();
    }

    private function upsertOrders($orders): int
    {
        $now = now();
        $records = collect($orders)->map(function ($order) use ($now) {
            $issued = Carbon::parse($order->issued);

            return [
                'order_id' => $order->order_id,
                'duration' => $order->duration,
                'is_buy_order' => $order->is_buy_order,
                'issued' => $issued,
                'expiry' => $issued->copy()->addDays($order->duration),
                'location_id' => $order->location_id,
                'min_volume' => $order->min_volume,
                'price' => $order->price,
                'range' => $order->range,
                'system_id' => $order->system_id,
                'type_id' => $order->type_id,
                'volume_remaining' => $order->volume_remain,
                'volume_total' => $order->volume_total,
                'updated_at' => $now,
                'created_at' => $now,
            ];
        })->values();

        if ($records->isEmpty()) {
            return 0;
        }

        MarketOrder::upsert($records->toArray(), ['order_id'], [
            'duration',
            'is_buy_order',
            'issued',
            'location_id',
            'min_volume',
            'price',
            'range',
            'system_id',
            'type_id',
            'volume_remaining',
            'volume_total',
            'expiry',
            'updated_at',
        ]);

        $this->incrementStat('orders_upserted', $records->count());

        return $records->count();
    }

    private function resetStats(): void
    {
        $this->lastStats = [
            'market_types_seen' => 0,
            'market_types_refreshed' => 0,
            'market_types_cached' => 0,
            'market_cache_memory_hits' => 0,
            'market_cache_store_hits' => 0,
            'market_cache_database_hits' => 0,
            'market_esi_requests' => 0,
            'market_pages' => 0,
            'market_orders_seen' => 0,
            'market_stale_deleted' => 0,
            'jita_types_seen' => 0,
            'jita_types_refreshed' => 0,
            'jita_types_cached' => 0,
            'jita_cache_memory_hits' => 0,
            'jita_cache_store_hits' => 0,
            'jita_cache_database_hits' => 0,
            'jita_esi_requests' => 0,
            'jita_pages' => 0,
            'jita_orders_seen' => 0,
            'jita_stale_deleted' => 0,
            'orders_upserted' => 0,
        ];
    }

    private function incrementStat(string $key, int $amount = 1): void
    {
        $this->lastStats[$key] = ($this->lastStats[$key] ?? 0) + $amount;
    }
}
