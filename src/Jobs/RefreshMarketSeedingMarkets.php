<?php

namespace Raikia\SeatMarketSeeding\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Raikia\SeatMarketSeeding\Services\MarketSeedingRefreshAll;
use Seat\Eveapi\Models\RefreshToken;

class RefreshMarketSeedingMarkets implements ShouldQueue, ShouldBeUnique
{
    const UNIQUE_ID = 'seat-market-seeding:refresh-all';

    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;
    public int $tries = 1;
    public int $uniqueFor = 900;

    public function __construct(
        private ?int $preferredTokenCharacterId = null
    ) {
    }

    public function handle(MarketSeedingRefreshAll $refreshAll): void
    {
        $preferredToken = $this->preferredTokenCharacterId
            ? RefreshToken::find($this->preferredTokenCharacterId)
            : null;

        $results = $refreshAll->refresh($preferredToken);

        logger()->info('Market seeding refresh job completed.', [
            'markets' => $results['markets'],
            'orders' => $results['orders'],
            'notifications' => $results['notifications'] ?? 0,
            'skipped' => $results['skipped'],
            'errors' => $results['errors'],
        ]);
    }

    public function uniqueId(): string
    {
        return self::UNIQUE_ID;
    }

    public function tags(): array
    {
        return ['market-seeding', 'refresh'];
    }
}
