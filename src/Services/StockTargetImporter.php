<?php

namespace Raikia\SeatMarketSeeding\Services;

use Illuminate\Support\Facades\DB;
use Raikia\SeatMarketSeeding\Models\SeededMarket;

class StockTargetImporter
{
    public function import(SeededMarket $market, array $items, string $mode, bool $keepHigherQuantity = false): int
    {
        return DB::transaction(function () use ($market, $items, $mode, $keepHigherQuantity) {
            if ($mode === 'replace') {
                $market->items()->delete();
            }

            foreach ($items as $item) {
                $target = $market->items()->firstOrNew([
                    'type_id' => $item['type_id'],
                ]);

                $target->type_name = $item['type_name'];
                $target->desired_quantity = $this->desiredQuantity($target, (int) $item['quantity'], $mode, $keepHigherQuantity);
                $target->warning_quantity = $target->warning_quantity ?: $target->desired_quantity;
                $target->save();
            }

            return count($items);
        });
    }

    private function desiredQuantity($target, int $quantity, string $mode, bool $keepHigherQuantity): int
    {
        if ($mode !== 'add') {
            return $quantity;
        }

        if ($keepHigherQuantity && $target->exists) {
            return max((int) $target->desired_quantity, $quantity);
        }

        return (int) $target->desired_quantity + $quantity;
    }
}
