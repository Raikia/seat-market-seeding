<?php

namespace Raikia\SeatMarketSeeding\Support;

use Illuminate\Support\Facades\Cache;

class MarketSeedingCache
{
    private const HISTORY_PRICE_VERSION_KEY = 'seat-market-seeding:history-prices:version';
    private const STRUCTURE_TOKEN_KEY = 'seat-market-seeding:structure-market-token-id';

    public static function historyPriceVersion(): int
    {
        return (int) Cache::get(self::HISTORY_PRICE_VERSION_KEY, 1);
    }

    public static function bumpHistoryPriceVersion(): void
    {
        Cache::forever(self::HISTORY_PRICE_VERSION_KEY, self::historyPriceVersion() + 1);
    }

    public static function historyPriceKey($typeIds): string
    {
        $typeIds = collect($typeIds)
            ->map(fn ($typeId) => (int) $typeId)
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return 'seat-market-seeding:history-prices:' . self::historyPriceVersion() . ':' . md5($typeIds->implode(','));
    }

    public static function structureTokenId(): ?int
    {
        $tokenId = Cache::get(self::STRUCTURE_TOKEN_KEY);

        return $tokenId ? (int) $tokenId : null;
    }

    public static function rememberStructureTokenId(?int $tokenId): void
    {
        if (!$tokenId) {
            Cache::forget(self::STRUCTURE_TOKEN_KEY);
            return;
        }

        Cache::put(self::STRUCTURE_TOKEN_KEY, (int) $tokenId, now()->addMinutes(10));
    }
}
