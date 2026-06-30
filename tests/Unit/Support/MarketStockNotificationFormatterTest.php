<?php

namespace Raikia\SeatMarketSeeding\Tests\Unit\Support;

use Raikia\SeatMarketSeeding\Support\MarketStockNotificationFormatter;
use Raikia\SeatMarketSeeding\Tests\TestCase;

class MarketStockNotificationFormatterTest extends TestCase
{
    public function test_transition_item_line_labels_stock_target_and_warning_quantities(): void
    {
        $line = MarketStockNotificationFormatter::transitionItemLine([
            'type_name' => 'Absolution',
            'current_quantity' => 3,
            'desired_quantity' => 5,
            'warning_quantity' => 4,
            'previous_status' => 'stocked',
        ]);

        $this->assertSame('Absolution: stock 3 / target 5 (warn at 4, was stocked)', $line);
    }

    public function test_restocked_item_line_labels_stock_and_target_quantities(): void
    {
        $line = MarketStockNotificationFormatter::restockedItemLine([
            'type_name' => 'Absolution',
            'current_quantity' => 3,
            'desired_quantity' => 5,
            'previous_status' => 'low',
        ]);

        $this->assertSame('Absolution: stock 3 / target 5 (was low)', $line);
    }

    public function test_item_lines_limits_rows_and_reports_remainder(): void
    {
        $lines = MarketStockNotificationFormatter::itemLines([
            ['type_name' => 'One'],
            ['type_name' => 'Two'],
            ['type_name' => 'Three'],
        ], 2, fn (array $item) => $item['type_name']);

        $this->assertSame([
            'One',
            'Two',
            '...and 1 more',
        ], $lines);
    }
}
