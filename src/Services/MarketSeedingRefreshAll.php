<?php

namespace Raikia\SeatMarketSeeding\Services;

use Raikia\SeatMarketSeeding\Models\SeededMarket;
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

        $markets = SeededMarket::with('items')
            ->orderBy('name')
            ->get();

        $structureToken = $preferredToken && $this->tokenHasStructureMarketScope($preferredToken)
            ? $preferredToken
            : $this->findStructureMarketToken();

        $refresh = app(EsiMarketOrderRefresh::class);
        $notifier = app(MarketStockTransitionNotifier::class);

        foreach ($markets as $market) {
            if ($market->is_structure && !$structureToken) {
                $message = sprintf('%s requires a token with %s.', $market->name, self::STRUCTURE_MARKET_SCOPE);
                $this->recordRefreshStatus($market, 'skipped', $message);
                $results['skipped'][] = $message;
                continue;
            }

            try {
                $orders = $refresh->refresh($market, $market->is_structure ? $structureToken : null);
                $results['orders'] += $orders;
                $results['notifications'] += $notifier->checkMarket($market);
                $this->recordRefreshStatus($market, 'success', 'Refresh completed successfully.', $orders);
                $results['markets']++;
            } catch (\Throwable $e) {
                $message = sprintf('%s: %s', $market->name, $e->getMessage());
                $this->recordRefreshStatus($market, 'error', $e->getMessage());
                $results['errors'][] = $message;
            }
        }

        return $results;
    }

    private function findStructureMarketToken(): ?RefreshToken
    {
        return RefreshToken::query()
            ->get()
            ->first(function (RefreshToken $token) {
                return $this->tokenHasStructureMarketScope($token);
            });
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
