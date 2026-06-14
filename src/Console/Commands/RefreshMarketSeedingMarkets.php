<?php

namespace Raikia\SeatMarketSeeding\Console\Commands;

use Illuminate\Console\Command;
use Raikia\SeatMarketSeeding\Services\MarketSeedingRefreshAll;

class RefreshMarketSeedingMarkets extends Command
{
    protected $signature = 'market-seeding:refresh';

    protected $description = 'Refresh configured market seeding market orders from ESI.';

    public function handle(MarketSeedingRefreshAll $refreshAll): int
    {
        $results = $refreshAll->refresh();

        $this->info(sprintf(
            'Refreshed %d market(s), updated %d order(s).',
            $results['markets'],
            $results['orders']
        ));

        foreach ($results['skipped'] as $message) {
            $this->warn('Skipped: ' . $message);
        }

        foreach ($results['errors'] as $message) {
            $this->error($message);
        }

        return empty($results['errors']) ? self::SUCCESS : self::FAILURE;
    }
}
