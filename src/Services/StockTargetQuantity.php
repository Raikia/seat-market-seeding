<?php

namespace Raikia\SeatMarketSeeding\Services;

use Raikia\SeatMarketSeeding\Models\SeededMarketItem;

class StockTargetQuantity
{
    public function desiredQuantity(?SeededMarketItem $target, int $quantity, string $mode, bool $keepHigherQuantity): int
    {
        if ($mode !== 'add') {
            return $quantity;
        }

        if ($keepHigherQuantity && $target && $target->exists) {
            return max((int) $target->desired_quantity, $quantity);
        }

        return ($target ? (int) $target->desired_quantity : 0) + $quantity;
    }

    public function warningQuantity(?SeededMarketItem $target, int $desiredQuantity, string $mode): int
    {
        if ($mode === 'add' && $target && $target->exists && (int) $target->warning_quantity > 0) {
            return (int) $target->warning_quantity;
        }

        return $this->defaultWarningQuantity($desiredQuantity);
    }

    public function defaultWarningQuantity(int $desiredQuantity): int
    {
        return max(1, (int) ceil($desiredQuantity * 0.33));
    }
}
