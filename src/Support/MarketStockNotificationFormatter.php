<?php

namespace Raikia\SeatMarketSeeding\Support;

class MarketStockNotificationFormatter
{
    public static function transitionItemLine(array $item): string
    {
        return sprintf(
            '%s: stock %s / target %s (warn at %s, was %s)',
            $item['type_name'],
            number_format($item['current_quantity']),
            number_format($item['desired_quantity']),
            number_format($item['warning_quantity']),
            $item['previous_status']
        );
    }

    public static function restockedItemLine(array $item): string
    {
        return sprintf(
            '%s: stock %s / target %s (was %s)',
            $item['type_name'],
            number_format($item['current_quantity']),
            number_format($item['desired_quantity']),
            $item['previous_status']
        );
    }

    public static function itemLines(array $items, int $limit, callable $lineFormatter): array
    {
        $items = collect($items);
        $lines = $items->take($limit)->map($lineFormatter)->all();

        if ($items->count() > count($lines)) {
            $lines[] = sprintf('...and %s more', number_format($items->count() - count($lines)));
        }

        return $lines;
    }
}
