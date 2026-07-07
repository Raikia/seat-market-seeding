<?php

namespace Raikia\SeatMarketSeeding\Services;

use Illuminate\Support\Facades\Schema;
use Raikia\SeatMarketSeeding\Models\SeededMarket;
use Raikia\SeatMarketSeeding\Support\MarketSeedingCache;
use Seat\Eseye\Exceptions\EsiScopeAccessDeniedException;
use Seat\Eseye\Exceptions\InvalidAuthenticationException;
use Seat\Eveapi\Models\Character\CharacterInfo;
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

        $marketRelations = ['items', 'refreshCharacter', 'trackedDoctrines'];

        if ($this->savedFittingTrackingAvailable()) {
            $marketRelations[] = 'trackedSavedFittings';
        }

        $markets = SeededMarket::with($marketRelations)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $refresh = app(EsiMarketOrderRefresh::class);
        $notifier = app(MarketStockTransitionNotifier::class);
        $doctrineSync = app(DoctrineTrackingSync::class);
        $savedFittingSync = app(SavedFittingTrackingSync::class);

        foreach ($markets as $market) {
            $startedAt = microtime(true);
            $structureToken = $market->is_structure
                ? $this->structureMarketTokenFor($market, $preferredToken)
                : null;
            $structureTokenLabel = $market->is_structure
                ? $this->refreshTokenLabel($structureToken, $market)
                : 'not required for station markets';

            if ($market->is_structure && !$structureToken) {
                $message = $this->structureTokenUnavailableMessage($market);
                $this->recordRefreshStatus($market, 'skipped', $message);
                $results['skipped'][] = $message;
                continue;
            }

            try {
                $doctrineStartedAt = microtime(true);
                $doctrineSync->syncMarket($market);
                $doctrineSeconds = round(microtime(true) - $doctrineStartedAt, 3);
                $savedFittingStartedAt = microtime(true);
                if ($this->savedFittingTrackingAvailable()) {
                    $savedFittingSync->syncMarket($market);
                }
                $savedFittingSeconds = round(microtime(true) - $savedFittingStartedAt, 3);
                $market->load('items');

                $esiStartedAt = microtime(true);
                $orders = $refresh->refresh($market, $market->is_structure ? $structureToken : null);
                $esiSeconds = round(microtime(true) - $esiStartedAt, 3);
                $results['orders'] += $orders;
                $notificationStartedAt = microtime(true);
                $results['notifications'] += $notifier->checkMarket($market, $refresh->expiredStaleQuantities());
                $notificationSeconds = round(microtime(true) - $notificationStartedAt, 3);
                $this->recordRefreshStatus($market, 'success', 'Refresh completed successfully.', $orders);
                $results['markets']++;

                logger()->info('Market seeding market refresh completed.', [
                    'market_id' => $market->id,
                    'market_name' => $market->name,
                    'refresh_token' => $structureTokenLabel,
                    'refresh_token_character_id' => optional($structureToken)->character_id,
                    'items' => $market->items->count(),
                    'orders' => $orders,
                    'seconds' => round(microtime(true) - $startedAt, 3),
                    'doctrine_seconds' => $doctrineSeconds,
                    'saved_fitting_seconds' => $savedFittingSeconds,
                    'esi_seconds' => $esiSeconds,
                    'notification_seconds' => $notificationSeconds,
                    'refresh_stats' => $refresh->getLastStats(),
                ]);
            } catch (\Throwable $e) {
                $refreshMessage = $this->refreshFailureMessage($market, $e);
                if ($market->is_structure) {
                    $refreshMessage = $this->appendRefreshTokenMessage($refreshMessage, $structureTokenLabel);
                }
                $message = sprintf('%s: %s', $market->name, $refreshMessage);
                $this->recordRefreshStatus($market, 'error', $refreshMessage);
                $results['errors'][] = $message;

                logger()->error('Market seeding market refresh failed.', [
                    'market_id' => $market->id,
                    'market_name' => $market->name,
                    'refresh_token' => $structureTokenLabel,
                    'refresh_token_character_id' => optional($structureToken)->character_id,
                    'items' => $market->items->count(),
                    'seconds' => round(microtime(true) - $startedAt, 3),
                    'error' => $e->getMessage(),
                    'refresh_message' => $refreshMessage,
                    'exception' => get_class($e),
                    'refresh_stats' => $refresh->getLastStats(),
                ]);
            }
        }

        if ($results['markets'] > 0 || !empty($results['skipped']) || !empty($results['errors'])) {
            MarketSeedingCache::bumpHistoryPriceVersion();
        }

        return $results;
    }

    private function structureMarketTokenFor(SeededMarket $market, ?RefreshToken $preferredToken = null): ?RefreshToken
    {
        if ($market->refresh_character_id) {
            $configuredToken = RefreshToken::find($market->refresh_character_id);

            return $configuredToken && $this->tokenHasStructureMarketScope($configuredToken)
                ? $configuredToken
                : null;
        }

        if ($preferredToken && $this->tokenHasStructureMarketScope($preferredToken)) {
            return $preferredToken;
        }

        return $this->findStructureMarketToken();
    }

    private function structureTokenUnavailableMessage(SeededMarket $market): string
    {
        if ($market->refresh_character_id) {
            $token = RefreshToken::find($market->refresh_character_id);
            $character = $market->refresh_character_name
                ?: optional($market->refreshCharacter)->name
                ?: CharacterInfo::query()->where('character_id', $market->refresh_character_id)->value('name')
                ?: ('Character #' . $market->refresh_character_id);

            if (!$token) {
                return sprintf(
                    '%s is configured to refresh with %s, but that character does not have an active refresh token. Re-authenticate that character or choose another refresh character.',
                    $market->name,
                    $character
                );
            }

            return sprintf(
                '%s is configured to refresh with %s, but that token is missing the %s scope required for structure market orders.',
                $market->name,
                $character,
                self::STRUCTURE_MARKET_SCOPE
            );
        }

        return sprintf(
            '%s requires a token with %s. No refresh character is configured, and no legacy automatic token was available.',
            $market->name,
            self::STRUCTURE_MARKET_SCOPE
        );
    }

    private function refreshTokenLabel(?RefreshToken $token, SeededMarket $market): string
    {
        if ($token) {
            $name = $market->refresh_character_id && (int) $market->refresh_character_id === (int) $token->character_id
                ? ($market->refresh_character_name ?: optional($market->refreshCharacter)->name)
                : null;

            $name = $name
                ?: CharacterInfo::query()->where('character_id', $token->character_id)->value('name')
                ?: ('Character #' . $token->character_id);

            return sprintf('%s (#%d)', $name, $token->character_id);
        }

        if ($market->refresh_character_id) {
            $name = $market->refresh_character_name
                ?: optional($market->refreshCharacter)->name
                ?: CharacterInfo::query()->where('character_id', $market->refresh_character_id)->value('name')
                ?: ('Character #' . $market->refresh_character_id);

            return sprintf('%s (#%d)', $name, $market->refresh_character_id);
        }

        return 'legacy automatic token selection';
    }

    private function appendRefreshTokenMessage(string $message, string $tokenLabel): string
    {
        return rtrim($message, '.') . '. Refresh token: ' . $tokenLabel . '.';
    }

    private function refreshFailureMessage(SeededMarket $market, \Throwable $e): string
    {
        $message = trim($e->getMessage());
        $code = (int) $e->getCode();
        $isAuthorizationMessage = str_contains(strtolower($message), 'not authorized')
            || str_contains(strtolower($message), 'unauthorized')
            || str_contains(strtolower($message), 'forbidden');

        if ($e instanceof EsiScopeAccessDeniedException) {
            return sprintf('ESI token is missing the %s scope required for structure market orders.', self::STRUCTURE_MARKET_SCOPE);
        }

        if ($e instanceof InvalidAuthenticationException || $code === 401) {
            return 'ESI rejected the refresh token. Re-authenticate a character with structure market access.';
        }

        if ($code === 403 || $isAuthorizationMessage) {
            if ($market->is_structure) {
                return 'ESI denied access to this structure market. The structure may be offline, the market service may be unavailable, or the refresh token character may not have access.';
            }

            return 'ESI denied access to this market endpoint.';
        }

        if ($code === 404 && $market->is_structure) {
            return 'ESI could not find this structure market. The structure may be offline, unanchored, or unavailable to the refresh token character.';
        }

        if ($code === 420 || $code === 429) {
            return 'ESI rate limited this refresh. It should succeed on a later scheduled run.';
        }

        if ($code >= 500) {
            return 'ESI returned a server error while refreshing this market. It should succeed once ESI recovers.';
        }

        return $message !== '' ? $message : 'Market refresh failed with an unknown ESI error.';
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
        $scopes = $token->scopes ?: [];

        if (is_string($scopes)) {
            $decoded = json_decode($scopes, true);
            $scopes = is_array($decoded) ? $decoded : [];
        }

        return in_array(self::STRUCTURE_MARKET_SCOPE, $scopes, true);
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

    private function savedFittingTrackingAvailable(): bool
    {
        return Schema::hasTable('seat_market_seeding_tracked_saved_fittings')
            && Schema::hasColumn('seat_market_seeding_item_sources', 'tracked_saved_fitting_id');
    }
}
