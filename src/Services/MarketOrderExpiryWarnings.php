<?php

namespace Raikia\SeatMarketSeeding\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Raikia\SeatMarketSeeding\Models\SeededMarket;
use Raikia\SeatMarketSeeding\Models\SeededMarketItem;
use Seat\Eveapi\Models\Character\CharacterInfo;
use Seat\Eveapi\Models\Market\CharacterOrder;
use Seat\Eveapi\Models\RefreshToken;

class MarketOrderExpiryWarnings
{
    private array $cache = [];

    public function forUser($user, int $days = 14): array
    {
        if (!$user) {
            return $this->emptyPayload($days);
        }

        $cacheKey = (int) $user->id . ':' . $days;

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $persistentCacheKey = 'seat-market-seeding:expiring-orders:' . now()->toDateString() . ':' . $cacheKey;

        return $this->cache[$cacheKey] = Cache::remember($persistentCacheKey, now()->addDay(), function () use ($user, $days) {
            return $this->buildForUser($user, $days);
        });
    }

    private function buildForUser($user, int $days): array
    {
        $markets = $this->visibleMarkets($user)
            ->with(['items' => function ($query) {
                $query->select('id', 'market_id', 'type_id', 'type_name');
            }])
            ->get(['id', 'name', 'location_id', 'location_name', 'role_id']);
        $characterIds = RefreshToken::withTrashed()
            ->where('user_id', $user->id)
            ->pluck('character_id')
            ->map(fn ($characterId) => (int) $characterId)
            ->unique()
            ->values();

        if ($markets->isEmpty() || $characterIds->isEmpty()) {
            return $this->emptyPayload($days);
        }

        $tracked = $this->trackedItemsByLocation($markets);
        $locationIds = $tracked->keys()->map(fn ($locationId) => (int) $locationId)->values();
        $typeIds = $tracked
            ->flatMap(fn (Collection $items) => $items->keys())
            ->map(fn ($typeId) => (int) $typeId)
            ->unique()
            ->values();

        if ($locationIds->isEmpty() || $typeIds->isEmpty()) {
            return $this->emptyPayload($days);
        }

        $characterNames = CharacterInfo::query()
            ->whereIn('character_id', $characterIds)
            ->pluck('name', 'character_id');
        $expiresBefore = now()->addDays($days);
        $earliestIssued = now()->subDays(90);
        $orders = CharacterOrder::query()
            ->whereIn('character_id', $characterIds)
            ->whereIn('location_id', $locationIds)
            ->whereIn('type_id', $typeIds)
            ->where(function ($query) {
                $query->where('is_buy_order', false)
                    ->orWhereNull('is_buy_order');
            })
            ->where('state', 'active')
            ->where('volume_remain', '>', 0)
            ->where('issued', '>=', $earliestIssued)
            ->get([
                'character_id',
                'order_id',
                'type_id',
                'location_id',
                'price',
                'volume_remain',
                'volume_total',
                'issued',
                'duration',
            ])
            ->map(function (CharacterOrder $order) use ($tracked, $characterNames) {
                $locationId = (int) $order->location_id;
                $typeId = (int) $order->type_id;
                $trackedItem = optional($tracked->get($locationId))->get($typeId);

                if (!$trackedItem) {
                    return null;
                }

                $issued = $order->issued ? Carbon::parse($order->issued) : null;
                $expiresAt = $issued ? $issued->copy()->addDays((int) $order->duration) : null;

                return [
                    'order_id' => (int) $order->order_id,
                    'character_id' => (int) $order->character_id,
                    'character_name' => $characterNames->get((int) $order->character_id) ?: 'Character #' . (int) $order->character_id,
                    'market_id' => (int) $trackedItem['market_id'],
                    'market_name' => $trackedItem['market_name'],
                    'location_name' => $trackedItem['location_name'],
                    'type_id' => $typeId,
                    'type_name' => $trackedItem['type_name'],
                    'quantity_remaining' => (int) $order->volume_remain,
                    'quantity_total' => (int) $order->volume_total,
                    'price' => (float) $order->price,
                    'listed_value' => (int) $order->volume_remain * (float) $order->price,
                    'expires_at' => $expiresAt,
                    'days_until_expiry' => $expiresAt ? now()->startOfDay()->diffInDays($expiresAt->copy()->startOfDay(), false) : null,
                ];
            })
            ->filter()
            ->filter(function (array $order) use ($expiresBefore) {
                return $order['expires_at']
                    && $order['expires_at']->greaterThanOrEqualTo(now())
                    && $order['expires_at']->lessThanOrEqualTo($expiresBefore);
            })
            ->sortBy([
                ['expires_at', 'asc'],
                ['listed_value', 'desc'],
            ])
            ->values()
            ->map(function (array $order) {
                $order['expires_at_order'] = $order['expires_at']->timestamp;
                $order['expires_at'] = $order['expires_at']->format('Y-m-d H:i');

                return $order;
            });

        return [
            'days' => $days,
            'count' => $orders->count(),
            'orders' => $orders,
            'total_value' => (float) $orders->sum('listed_value'),
            'market_count' => $orders->pluck('market_id')->unique()->count(),
        ];
    }

    private function visibleMarkets($user)
    {
        if ($user->isAdmin()) {
            return SeededMarket::query();
        }

        $roleIds = $user->roles->pluck('id');

        return SeededMarket::query()
            ->where(function ($query) use ($roleIds) {
                $query->whereNull('role_id')
                    ->orWhereIn('role_id', $roleIds);
            });
    }

    private function trackedItemsByLocation(Collection $markets): Collection
    {
        return $markets
            ->flatMap(function (SeededMarket $market) {
                return $market->items->map(function (SeededMarketItem $item) use ($market) {
                    return [
                        'location_id' => (int) $market->location_id,
                        'type_id' => (int) $item->type_id,
                        'market_id' => (int) $market->id,
                        'market_name' => $market->name,
                        'location_name' => $market->location_name,
                        'type_name' => $item->type_name,
                    ];
                });
            })
            ->groupBy('location_id')
            ->map(fn (Collection $items) => $items->keyBy('type_id'));
    }

    private function emptyPayload(int $days): array
    {
        return [
            'days' => $days,
            'count' => 0,
            'orders' => collect(),
            'total_value' => 0.0,
            'market_count' => 0,
        ];
    }
}
