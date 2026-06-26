<?php

namespace Raikia\SeatMarketSeeding\Tests\Unit\Models;

use Raikia\SeatMarketSeeding\Models\MarketSeedingTargetHistory;
use Raikia\SeatMarketSeeding\Tests\TestCase;

class MarketSeedingTargetHistoryTest extends TestCase
{
    public function test_it_formats_known_change_type_labels(): void
    {
        $this->assertSame('Manual edit', $this->history(MarketSeedingTargetHistory::CHANGE_MANUAL)->changeTypeLabel());
        $this->assertSame('Bulk import', $this->history(MarketSeedingTargetHistory::CHANGE_BULK_IMPORT)->changeTypeLabel());
        $this->assertSame('Saved fit import', $this->history(MarketSeedingTargetHistory::CHANGE_SAVED_FITTING)->changeTypeLabel());
        $this->assertSame('Doctrine sync', $this->history(MarketSeedingTargetHistory::CHANGE_DOCTRINE)->changeTypeLabel());
        $this->assertSame('Recommendation', $this->history(MarketSeedingTargetHistory::CHANGE_RECOMMENDATION)->changeTypeLabel());
        $this->assertSame('Market clear', $this->history(MarketSeedingTargetHistory::CHANGE_CLEAR)->changeTypeLabel());
    }

    public function test_it_formats_unknown_change_type_safely(): void
    {
        $this->assertSame('One off script', $this->history('one_off_script')->changeTypeLabel());
    }

    private function history(string $changeType): MarketSeedingTargetHistory
    {
        return new MarketSeedingTargetHistory(['change_type' => $changeType]);
    }
}
