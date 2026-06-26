<?php

namespace Raikia\SeatMarketSeeding\Services;

use Raikia\SeatMarketSeeding\Models\MarketSeedingTargetHistory;
use Raikia\SeatMarketSeeding\Models\SeededMarket;

class StockTargetImporter
{
    private StockTargetProjector $projector;

    public function __construct(StockTargetProjector $projector)
    {
        $this->projector = $projector;
    }

    public function import(
        SeededMarket $market,
        array $items,
        string $mode,
        bool $keepHigherQuantity = false,
        int $warningPercentage = 33,
        string $changeType = MarketSeedingTargetHistory::CHANGE_BULK_IMPORT
    ): int
    {
        return $this->projector->importManualTargets($market, $items, $mode, $keepHigherQuantity, $warningPercentage, $changeType);
    }
}
