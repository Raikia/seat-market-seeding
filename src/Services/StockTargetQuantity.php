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
        return $this->warningQuantityFromPercentage($desiredQuantity, 33);
    }

    public function warningQuantityFromPercentage(int $desiredQuantity, int $percentage): int
    {
        $desiredQuantity = max(1, $desiredQuantity);
        $percentage = max(0, min(100, $percentage));

        return $this->clampWarningQuantity((int) ceil($desiredQuantity * ($percentage / 100)), $desiredQuantity);
    }

    public function scaleWarningQuantity(int $desiredQuantity, int $currentTarget, int $currentWarning): int
    {
        $desiredQuantity = max(1, $desiredQuantity);
        $currentTarget = max(1, $currentTarget);
        $currentWarning = max(0, $currentWarning);

        if ($currentWarning === 0) {
            return 0;
        }

        return $this->clampWarningQuantity((int) ceil($desiredQuantity * ($currentWarning / $currentTarget)), $desiredQuantity);
    }

    public function clampWarningQuantity(int $warningQuantity, int $desiredQuantity): int
    {
        return max(0, min(max(0, $desiredQuantity), $warningQuantity));
    }
}
