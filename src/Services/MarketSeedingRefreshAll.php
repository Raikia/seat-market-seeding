<?php

namespace Raikia\SeatMarketSeeding\Services;

use Raikia\SeatMarketSeeding\Models\SeededMarket;
use Raikia\SeatMarketSeeding\Support\MarketSeedingCache;
use Seat\Eveapi\Models\RefreshToken;

class MarketSeedingRefreshAll
{
    const STRUCTURE_MARKET_SCOPE = 'esi-markets.structure_markets.v1';

    public function refresh(?RefreshToken $preferredToken = null): array
    {
        $results = [
            'markets' => 0,
            'orders' => 0,
            'notifications' => 0,
            'errors' => [],
            'skipped' => [],
        ];

        $markets = SeededMarket::with('items', 'trackedDoctrines')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $structureToken = null;

        if ($markets->contains('is_structure', true)) {
            $structureToken = $preferredToken && $this->tokenHasStructureMarketScope($preferredToken)
                ? $preferredToken
                : $this->findStructureMarketToken();
        }

        $refresh = app(EsiMarketOrderRefresh::class);
        $notifier = app(MarketStockTransitionNotifier::class);
        $doctrineSync = app(DoctrineTrackingSync::class);

        foreach ($markets as $market) {
            $startedAt = microtime(true);

            if ($market->is_structure && !$structureToken) {
                $message = sprintf('%s requires a token with %s.', $market->name, self::STRUCTURE_MARKET_SCOPE);
                $this->recordRefreshStatus($market, 'skipped', $message);
                $results['skipped'][] = $message;
                continue;
            }

            try {
                $doctrineStartedAt = microtime(true);
                $doctrineSync->syncMarket($market);
                $doctrineSeconds = round(microtime(true) - $doctrineStartedAt, 3);
                $market->load('items');

                $esiStartedAt = microtime(true);
                $orders = $refresh->refresh($market, $market->is_structure ? $structureToken : null);
                $esiSeconds = round(microtime(true) - $esiStartedAt, 3);
                $results['orders'] += $orders;
                $notificationStartedAt = microtime(true);
                $results['notifications'] += $notifier->checkMarket($market);
                $notificationSeconds = round(microtime(true) - $notificationStartedAt, 3);
                $this->recordRefreshStatus($market, 'success', 'Refresh completed successfully.', $orders);
                $results['markets']++;

                logger()->info('Market seeding market refresh completed.', [
                    'market_id' => $market->id,
                    'market_name' => $market->name,
                    'items' => $market->items->count(),
                    'orders' => $orders,
                    'seconds' => round(microtime(true) - $startedAt, 3),
                    'doctrine_seconds' => $doctrineSeconds,
                    'esi_seconds' => $esiSeconds,
                    'notification_seconds' => $notificationSeconds,
                    'refresh_stats' => $refresh->getLastStats(),
                ]);
            } catch (\Throwable $e) {
                $message = sprintf('%s: %s', $market->name, $e->getMessage());
                $this->recordRefreshStatus($market, 'error', $e->getMessage());
                $results['errors'][] = $message;

                logger()->warning('Market seeding market refresh failed.', [
                    'market_id' => $market->id,
                    'market_name' => $market->name,
                    'items' => $market->items->count(),
                    'seconds' => round(microtime(true) - $startedAt, 3),
                    'error' => $e->getMessage(),
                    'refresh_stats' => $refresh->getLastStats(),
                ]);
            }
        }

        if ($results['markets'] > 0 || !empty($results['skipped']) || !empty($results['errors'])) {
            MarketSeedingCache::bumpHistoryPriceVersion();
        }

        return $results;
    }

    private function findStructureMarketToken(): ?RefreshToken
    {
        $cachedTokenId = MarketSeedingCache::structureTokenId();

        if ($cachedTokenId) {
            $cachedToken = RefreshToken::find($cachedTokenId);

            if ($cachedToken && $this->tokenHasStructureMarketScope($cachedToken)) {
                return $cachedToken;
            }

            MarketSeedingCache::rememberStructureTokenId(null);
        }

        $token = RefreshToken::query()
            ->whereJsonContains('scopes', self::STRUCTURE_MARKET_SCOPE)
            ->orderByDesc('updated_at')
            ->first();

        MarketSeedingCache::rememberStructureTokenId(optional($token)->character_id);

        return $token;
    }

    private function tokenHasStructureMarketScope(RefreshToken $token): bool
    {
        return in_array(self::STRUCTURE_MARKET_SCOPE, $token->scopes ?: [], true);
    }

    private function recordRefreshStatus(SeededMarket $market, string $status, string $message, int $orders = 0): void
    {
        $market->update([
            'last_refreshed_at' => now(),
            'last_refresh_status' => $status,
            'last_refresh_message' => $message,
            'last_refresh_orders' => $orders,
        ]);
    }
}
