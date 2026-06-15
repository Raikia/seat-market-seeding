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

    public function preview(SeededMarket $market, array $items, string $mode, bool $keepHigherQuantity = false): array
    {
        $market->loadMissing('items', 'itemSources.trackedDoctrine');

        $existing = $market->items->keyBy('type_id');
        $sources = $market->itemSources->groupBy('type_id');
        $manualSources = $market->itemSources
            ->where('source_type', MarketSeedingItemSource::SOURCE_MANUAL)
            ->keyBy('type_id');
        $rows = collect($items)->map(function (array $item) use ($existing, $sources, $manualSources, $mode, $keepHigherQuantity) {
            $current = $existing->get($item['type_id']);
            $currentQuantity = $current ? (int) $current->desired_quantity : 0;
            $currentManualQuantity = (int) optional($manualSources->get($item['type_id']))->quantity;
            $importQuantity = (int) $item['quantity'];
            $newManualQuantity = $this->manualQuantity($currentManualQuantity, $importQuantity, $mode, $keepHigherQuantity);
            $newQuantity = $this->effectiveQuantity($sources->get($item['type_id'], collect()), $newManualQuantity);

            return [
                'type_id' => (int) $item['type_id'],
                'type_name' => $item['type_name'],
                'current_quantity' => $currentQuantity,
                'import_quantity' => $importQuantity,
                'new_quantity' => $newQuantity,
                'warning_quantity' => $this->quantities->warningQuantity($current, $newQuantity, $mode),
                'action' => $this->action($currentQuantity, $newQuantity, (bool) $current, $mode),
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

    private function effectiveQuantity($sources, int $manualQuantity): int
    {
        $addQuantity = 0;
        $maxQuantity = 0;

        collect($sources)
            ->where('source_type', MarketSeedingItemSource::SOURCE_DOCTRINE)
            ->each(function (MarketSeedingItemSource $source) use (&$addQuantity, &$maxQuantity) {
                $mergeMode = optional($source->trackedDoctrine)->merge_mode ?: MarketSeedingTrackedDoctrine::MERGE_MAX;

                if ($mergeMode === MarketSeedingTrackedDoctrine::MERGE_ADD) {
                    $addQuantity += (int) $source->quantity;
                    return;
                }

                $maxQuantity = max($maxQuantity, (int) $source->quantity);
            });

        return max($manualQuantity, $maxQuantity) + $addQuantity;
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
