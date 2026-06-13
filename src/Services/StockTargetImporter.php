<?php

namespace Raikia\SeatMarketSeeding\Services;

use Illuminate\Support\Facades\DB;
use Raikia\SeatMarketSeeding\Models\SeededMarket;

class StockTargetImporter
{
    public function import(SeededMarket $market, array $items, string $mode): int
    {
        return DB::transaction(function () use ($market, $items, $mode) {
            if ($mode === 'replace') {
                $market->items()->delete();
            }

            foreach ($items as $item) {
                $target = $market->items()->firstOrNew([
                    'type_id' => $item['type_id'],
                ]);

                $target->type_name = $item['type_name'];
                $target->desired_quantity = $mode === 'add'
                    ? $target->desired_quantity + $item['quantity']
                    : $item['quantity'];
                $target->warning_quantity = $target->warning_quantity ?: $target->desired_quantity;
                $target->save();
            }

            return count($items);
        });
    }
}
