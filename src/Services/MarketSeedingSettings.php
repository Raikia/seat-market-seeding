<?php

namespace Raikia\SeatMarketSeeding\Services;

use Raikia\SeatMarketSeeding\Models\MarketSeedingSetting;

class MarketSeedingSettings
{
    const HISTORY_RETENTION_DAYS = 'history_retention_days';
    const RECOMMENDATION_SALES_DAYS = 'recommendation_sales_days';
    const RECOMMENDATION_BUFFER_PERCENTAGE = 'recommendation_buffer_percentage';
    const DEFAULT_HISTORY_RETENTION_DAYS = 365;
    const DEFAULT_RECOMMENDATION_SALES_DAYS = 14;
    const DEFAULT_RECOMMENDATION_BUFFER_PERCENTAGE = 25;

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

    public function recommendationSalesDays(): int
    {
        $setting = MarketSeedingSetting::find(self::RECOMMENDATION_SALES_DAYS);
        $days = $setting ? (int) $setting->value : self::DEFAULT_RECOMMENDATION_SALES_DAYS;

        return max(1, $days);
    }

    public function recommendationBufferPercentage(): int
    {
        $setting = MarketSeedingSetting::find(self::RECOMMENDATION_BUFFER_PERCENTAGE);
        $percentage = $setting ? (int) $setting->value : self::DEFAULT_RECOMMENDATION_BUFFER_PERCENTAGE;

        return max(0, $percentage);
    }

    public function setRecommendationSalesDays(int $days): void
    {
        MarketSeedingSetting::updateOrCreate(
            ['setting' => self::RECOMMENDATION_SALES_DAYS],
            ['value' => (string) max(1, $days)]
        );
    }

    public function setRecommendationBufferPercentage(int $percentage): void
    {
        MarketSeedingSetting::updateOrCreate(
            ['setting' => self::RECOMMENDATION_BUFFER_PERCENTAGE],
            ['value' => (string) max(0, $percentage)]
        );
    }
}
