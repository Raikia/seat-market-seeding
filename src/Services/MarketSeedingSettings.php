<?php

namespace Raikia\SeatMarketSeeding\Services;

use Raikia\SeatMarketSeeding\Models\MarketSeedingSetting;

class MarketSeedingSettings
{
    const HISTORY_RETENTION_DAYS = 'history_retention_days';
    const DEFAULT_HISTORY_RETENTION_DAYS = 365;

    public function historyRetentionDays(): int
    {
        $setting = MarketSeedingSetting::find(self::HISTORY_RETENTION_DAYS);
        $days = $setting ? (int) $setting->value : self::DEFAULT_HISTORY_RETENTION_DAYS;

        return max(1, $days);
    }

    public function setHistoryRetentionDays(int $days): void
    {
        MarketSeedingSetting::updateOrCreate(
            ['setting' => self::HISTORY_RETENTION_DAYS],
            ['value' => (string) max(1, $days)]
        );
    }
}
