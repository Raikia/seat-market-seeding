<?php

namespace Raikia\SeatMarketSeeding\Services;

use Carbon\Carbon;
use Raikia\SeatMarketSeeding\Models\SeededMarket;
use Seat\Eveapi\Models\Market\MarketOrder;
use Seat\Eveapi\Models\RefreshToken;
use Seat\Eveapi\Services\EseyeClient;

class EsiMarketOrderRefresh
{
    const THE_FORGE_REGION_ID = 10000002;
    const JITA_STATION_ID = 60003760;

    private array $refreshedLocationTypes = [];

    public function refresh(SeededMarket $market, ?RefreshToken $refreshToken = null): int
    {
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

    private function refreshStationMarket(SeededMarket $market): int
    {
        return $this->refreshRegionLocationTypeOrders(
            $market->region_id,
            $market->location_id,
            $market->items->pluck('type_id')->all()
        );
    }

    private function refreshJitaOrders(SeededMarket $market): int
    {
        return $this->refreshRegionLocationTypeOrders(
            self::THE_FORGE_REGION_ID,
            self::JITA_STATION_ID,
            $market->items->pluck('type_id')->all()
        );
    }

    private function refreshRegionLocationTypeOrders(int $regionId, int $locationId, array $typeIds): int
    {
        $client = new EseyeClient();
        $count = 0;

        foreach (collect($typeIds)->map(fn ($typeId) => (int) $typeId)->unique() as $typeId) {
            if ($this->hasRefreshedLocationType($locationId, $typeId)) {
                continue;
            }

            $page = 1;
            $orders = collect();

            do {
                $response = $client->setQueryString([
                    'order_type' => 'sell',
                    'type_id' => $typeId,
                    'page' => $page,
                ])->invoke('get', '/markets/{region_id}/orders/', [
                    'region_id' => $regionId,
                ]);

                $orders = $orders->merge(
                    collect($response->getBody())
                        ->where('location_id', $locationId)
                );

                $pages = $response->getPagesCount() ?: 1;
                $page++;
            } while ($page <= $pages);

            $count += $this->replaceSellOrders($locationId, [$typeId], $orders);
            $this->markLocationTypeRefreshed($locationId, $typeId);
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
        $orders = collect();

        do {
            $response = $client->setQueryString([
                'page' => $page,
            ])->invoke('get', '/markets/structures/{structure_id}/', [
                'structure_id' => $market->location_id,
            ]);

            $orders = $orders->merge(
                collect($response->getBody())
                    ->where('is_buy_order', false)
                    ->whereIn('type_id', $trackedTypeIds)
                    ->map(function ($order) use ($market) {
                        $order->location_id = $market->location_id;
                        $order->system_id = $order->system_id ?? $market->solar_system_id ?? 0;

                        return $order;
                    })
            );

            $pages = $response->getPagesCount() ?: 1;
            $page++;
        } while ($page <= $pages);

        return $this->replaceSellOrders($market->location_id, $trackedTypeIds, $orders);
    }

    private function replaceSellOrders(int $locationId, array $typeIds, $orders): int
    {
        MarketOrder::query()
            ->where('location_id', $locationId)
            ->whereIn('type_id', $typeIds)
            ->where('is_buy_order', false)
            ->delete();

        return $this->upsertOrders($orders);
    }

    private function hasRefreshedLocationType(int $locationId, int $typeId): bool
    {
        return array_key_exists($this->locationTypeKey($locationId, $typeId), $this->refreshedLocationTypes);
    }

    private function markLocationTypeRefreshed(int $locationId, int $typeId): void
    {
        $this->refreshedLocationTypes[$this->locationTypeKey($locationId, $typeId)] = true;
    }

    private function locationTypeKey(int $locationId, int $typeId): string
    {
        return sprintf('%d:%d', $locationId, $typeId);
    }

    private function upsertOrders($orders): int
    {
        $records = collect($orders)->map(function ($order) {
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
                'updated_at' => now(),
                'created_at' => now(),
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

        return $records->count();
    }
}
