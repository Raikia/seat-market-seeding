<?php

namespace Raikia\SeatMarketSeeding\Tests\Unit\Services;

use Raikia\SeatMarketSeeding\Services\MarketSeedingSettings;
use Raikia\SeatMarketSeeding\Tests\TestCase;

class MarketSeedingSettingsTest extends TestCase
{
    public function test_it_returns_defaults_when_settings_are_missing(): void
    {
        $settings = app(MarketSeedingSettings::class);

        $this->assertSame(365, $settings->historyRetentionDays());
        $this->assertSame(120, $settings->jitaPriceRefreshMinutes());
        $this->assertSame(14, $settings->recommendationSalesDays());
        $this->assertSame(25, $settings->recommendationBufferPercentage());
    }

    public function test_it_clamps_minimum_settings_values(): void
    {
        $settings = app(MarketSeedingSettings::class);

        $settings->setHistoryRetentionDays(-100);
        $settings->setJitaPriceRefreshMinutes(1);
        $settings->setRecommendationSalesDays(0);
        $settings->setRecommendationBufferPercentage(-25);

        $this->assertSame(1, $settings->historyRetentionDays());
        $this->assertSame(5, $settings->jitaPriceRefreshMinutes());
        $this->assertSame(1, $settings->recommendationSalesDays());
        $this->assertSame(0, $settings->recommendationBufferPercentage());
    }
}
