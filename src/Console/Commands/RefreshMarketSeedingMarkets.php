<?php

namespace Raikia\SeatMarketSeeding\Console\Commands;

use Illuminate\Console\Command;
use Raikia\SeatMarketSeeding\Jobs\RefreshMarketSeedingMarkets as RefreshMarketSeedingMarketsJob;

class RefreshMarketSeedingMarkets extends Command
{
    protected $signature = 'market-seeding:refresh';

    protected $description = 'Refresh configured market seeding market orders from ESI.';

    public function handle(): int
    {
        RefreshMarketSeedingMarketsJob::dispatch();

        $this->info('Market seeding refresh job queued.');

        return self::SUCCESS;
    }
}
