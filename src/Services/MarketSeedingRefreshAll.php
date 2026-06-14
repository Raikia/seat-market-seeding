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

        foreach ($markets as $market) {
            if ($market->is_structure && !$structureToken) {
                $results['skipped'][] = sprintf('%s requires a token with %s.', $market->name, self::STRUCTURE_MARKET_SCOPE);
                continue;
            }

            try {
                $results['orders'] += $refresh->refresh($market, $market->is_structure ? $structureToken : null);
                $results['markets']++;
            } catch (\Throwable $e) {
                $results['errors'][] = sprintf('%s: %s', $market->name, $e->getMessage());
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
}
