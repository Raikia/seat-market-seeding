<?php

namespace Raikia\SeatMarketSeeding\Services;

use Raikia\SeatMarketSeeding\Models\SeededMarket;
use Raikia\SeatMarketSeeding\Models\MarketSeedingItemSource;
use Raikia\SeatMarketSeeding\Models\MarketSeedingTrackedDoctrine;

class StockTargetPreviewer
{
    private StockTargetQuantity $quantities;

    public function __construct(StockTargetQuantity $quantities)
    {
        $this->quantities = $quantities;
    }

    public function preview(SeededMarket $market, array $items, string $mode, bool $keepHigherQuantity = false, int $warningPercentage = 33): array
    {
        $market->loadMissing('items', 'itemSources.trackedDoctrine');

        $existing = $market->items->keyBy('type_id');
        $sources = $market->itemSources->groupBy('type_id');
        $manualSources = $market->itemSources
            ->where('source_type', MarketSeedingItemSource::SOURCE_MANUAL)
            ->keyBy('type_id');
        $rows = collect($items)->map(function (array $item) use ($existing, $sources, $manualSources, $mode, $keepHigherQuantity, $warningPercentage) {
            $current = $existing->get($item['type_id']);
            $currentQuantity = $current ? (int) $current->desired_quantity : 0;
            $currentManualQuantity = (int) optional($manualSources->get($item['type_id']))->quantity;
            $importQuantity = (int) $item['quantity'];
            $newManualQuantity = $this->manualQuantity($currentManualQuantity, $importQuantity, $mode, $keepHigherQuantity);
            $projection = $this->effectiveProjection($sources->get($item['type_id'], collect()), $newManualQuantity, $warningPercentage);

            return [
                'type_id' => (int) $item['type_id'],
                'type_name' => $item['type_name'],
                'current_quantity' => $currentQuantity,
                'import_quantity' => $importQuantity,
                'new_quantity' => $projection['quantity'],
                'warning_quantity' => $projection['warning_quantity'],
                'action' => $this->action($currentQuantity, $projection['quantity'], (bool) $current, $mode),
            ];
        })->values();

        return [
            'summary' => [
                'total' => $rows->count(),
                'new' => $rows->where('action', 'new')->count(),
                'increase' => $rows->where('action', 'increase')->count(),
                'reduce' => $rows->where('action', 'reduce')->count(),
                'replace' => $rows->where('action', 'replace')->count(),
                'unchanged' => $rows->where('action', 'unchanged')->count(),
            ],
            'rows' => $rows,
        ];
    }

    private function manualQuantity(int $currentManualQuantity, int $importQuantity, string $mode, bool $keepHigherQuantity): int
    {
        if ($mode !== 'add') {
            return $importQuantity;
        }

        if ($keepHigherQuantity) {
            return max($currentManualQuantity, $importQuantity);
        }

        return $currentManualQuantity + $importQuantity;
    }

    private function effectiveProjection($sources, int $manualQuantity, int $warningPercentage): array
    {
        $addQuantity = 0;
        $addWarningQuantity = 0;
        $maxQuantity = 0;
        $maxWarningQuantity = 0;

        collect($sources)
            ->where('source_type', MarketSeedingItemSource::SOURCE_DOCTRINE)
            ->each(function (MarketSeedingItemSource $source) use (&$addQuantity, &$addWarningQuantity, &$maxQuantity, &$maxWarningQuantity) {
                $mergeMode = optional($source->trackedDoctrine)->merge_mode ?: MarketSeedingTrackedDoctrine::MERGE_MAX;
                $quantity = (int) $source->quantity;
                $warningQuantity = (int) ($source->warning_quantity ?: $this->quantities->defaultWarningQuantity($quantity));

                if ($mergeMode === MarketSeedingTrackedDoctrine::MERGE_ADD) {
                    $addQuantity += $quantity;
                    $addWarningQuantity += $warningQuantity;
                    return;
                }

                if ($quantity > $maxQuantity) {
                    $maxQuantity = $quantity;
                    $maxWarningQuantity = $warningQuantity;
                } elseif ($quantity === $maxQuantity) {
                    $maxWarningQuantity = max($maxWarningQuantity, $warningQuantity);
                }
            });

        $manualWarningQuantity = $this->warningQuantityFromPercentage($manualQuantity, $warningPercentage);
        $baseWarningQuantity = $manualQuantity >= $maxQuantity
            ? $manualWarningQuantity
            : $maxWarningQuantity;

        return [
            'quantity' => max($manualQuantity, $maxQuantity) + $addQuantity,
            'warning_quantity' => max(1, $baseWarningQuantity + $addWarningQuantity),
        ];
    }

    private function warningQuantityFromPercentage(int $quantity, int $percentage): int
    {
        $percentage = max(1, min(100, $percentage));

        return max(1, (int) ceil(max(1, $quantity) * ($percentage / 100)));
    }

    private function action(int $currentQuantity, int $newQuantity, bool $exists, string $mode): string
    {
        if (!$exists) {
            return 'new';
        }

        if ($currentQuantity === $newQuantity) {
            return 'unchanged';
        }

        if ($mode !== 'add') {
            return $newQuantity > $currentQuantity ? 'replace' : 'reduce';
        }

        return $newQuantity > $currentQuantity ? 'increase' : 'unchanged';
    }
}
