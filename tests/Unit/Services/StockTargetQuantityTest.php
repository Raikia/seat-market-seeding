<?php

namespace Raikia\SeatMarketSeeding\Tests\Unit\Services;

use Raikia\SeatMarketSeeding\Services\StockTargetQuantity;
use Raikia\SeatMarketSeeding\Tests\TestCase;

class StockTargetQuantityTest extends TestCase
{
    public function test_warning_quantity_from_percentage_allows_zero_and_clamps_to_target(): void
    {
        $quantity = app(StockTargetQuantity::class);

        $this->assertSame(0, $quantity->warningQuantityFromPercentage(50, 0));
        $this->assertSame(17, $quantity->warningQuantityFromPercentage(50, 33));
        $this->assertSame(50, $quantity->warningQuantityFromPercentage(50, 500));
    }

    public function test_scaled_warning_preserves_current_ratio_and_never_exceeds_target(): void
    {
        $quantity = app(StockTargetQuantity::class);

        $this->assertSame(66, $quantity->scaleWarningQuantity(200, 100, 33));
        $this->assertSame(0, $quantity->scaleWarningQuantity(200, 100, 0));
        $this->assertSame(5, $quantity->scaleWarningQuantity(5, 100, 200));
    }
}
