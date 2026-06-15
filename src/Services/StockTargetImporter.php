<?php

namespace Raikia\SeatMarketSeeding\Services;

use Illuminate\Support\Facades\DB;
use Raikia\SeatMarketSeeding\Models\SeededMarket;

class StockTargetImporter
{
    private StockTargetQuantity $quantities;

    public function __construct(StockTargetQuantity $quantities)
    {
        $this->quantities = $quantities;
    }

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
                $target->desired_quantity = $this->quantities->desiredQuantity($target, (int) $item['quantity'], $mode, $keepHigherQuantity);
                $target->warning_quantity = $this->quantities->warningQuantity($target, $target->desired_quantity, $mode);
                $target->save();
            }

            return count($items);
        });
    }
}
