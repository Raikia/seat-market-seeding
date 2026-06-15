<?php

namespace Raikia\SeatMarketSeeding\Services;

use Raikia\SeatMarketSeeding\Models\MarketSeedingItemSource;
use Raikia\SeatMarketSeeding\Models\MarketSeedingTrackedDoctrine;
use Raikia\SeatMarketSeeding\Models\SeededMarket;

class StockTargetPreviewer
{
    private StockTargetProjector $projector;

    public function __construct(StockTargetProjector $projector)
    {
        $this->projector = $projector;
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
            $projection = $this->projector->projectSources(
                $sources->get($item['type_id'], collect()),
                $newManualQuantity,
                $this->warningQuantityFromPercentage($newManualQuantity, $warningPercentage),
                $mode !== 'replace'
            );

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

    public function previewDoctrine(SeededMarket $market, int $doctrineId, string $doctrineName, array $items, int $warningPercentage, string $mergeMode): array
    {
        $market->loadMissing('items', 'itemSources.trackedDoctrine');

        $existing = $market->items->keyBy('type_id');
        $previousDoctrineSources = $market->itemSources
            ->where('source_type', MarketSeedingItemSource::SOURCE_DOCTRINE)
            ->filter(function (MarketSeedingItemSource $source) use ($doctrineId) {
                return (int) optional($source->trackedDoctrine)->doctrine_id === $doctrineId;
            });
        $remainingSources = $market->itemSources->reject(function (MarketSeedingItemSource $source) use ($doctrineId) {
            return $source->source_type === MarketSeedingItemSource::SOURCE_DOCTRINE
                && (int) optional($source->trackedDoctrine)->doctrine_id === $doctrineId;
        });
        $newSources = collect($items)->map(function (array $item) use ($market, $doctrineId, $doctrineName, $warningPercentage, $mergeMode) {
            $trackedDoctrine = new MarketSeedingTrackedDoctrine([
                'market_id' => $market->id,
                'doctrine_id' => $doctrineId,
                'doctrine_name' => $doctrineName,
                'merge_mode' => $mergeMode,
            ]);

            $source = new MarketSeedingItemSource([
                'market_id' => $market->id,
                'source_type' => MarketSeedingItemSource::SOURCE_DOCTRINE,
                'source_key' => 'doctrine:' . $doctrineId,
                'type_id' => (int) $item['type_id'],
                'type_name' => $item['type_name'],
                'quantity' => (int) $item['quantity'],
                'warning_quantity' => $this->warningQuantityFromPercentage((int) $item['quantity'], $warningPercentage),
            ]);
            $source->setRelation('trackedDoctrine', $trackedDoctrine);

            return $source;
        });

        $projectedSources = collect($remainingSources->values()->all())
            ->merge($newSources->values())
            ->groupBy('type_id');
        $previousTypeIds = $previousDoctrineSources->pluck('type_id');
        $newTypeIds = $newSources->pluck('type_id');
        $typeIds = $previousTypeIds->merge($newTypeIds)->unique()->values();
        $newQuantities = $newSources->groupBy('type_id')->map(function ($sources) {
            return (int) $sources->sum('quantity');
        });

        $rows = $typeIds->map(function (int $typeId) use ($existing, $projectedSources, $newQuantities) {
            $current = $existing->get($typeId);
            $projection = $this->projector->projectSources($projectedSources->get($typeId, collect()));
            $newQuantity = (int) $projection['quantity'];
            $currentQuantity = $current ? (int) $current->desired_quantity : 0;

            return [
                'type_id' => $typeId,
                'type_name' => $projection['type_name'] ?: optional($current)->type_name,
                'current_quantity' => $currentQuantity,
                'import_quantity' => (int) $newQuantities->get($typeId, 0),
                'new_quantity' => $newQuantity,
                'warning_quantity' => (int) $projection['warning_quantity'],
                'action' => $this->action($currentQuantity, $newQuantity, (bool) $current, 'doctrine'),
            ];
        })->filter(function (array $row) {
            return $row['type_name'] && ($row['current_quantity'] > 0 || $row['new_quantity'] > 0 || $row['import_quantity'] > 0);
        })->sortBy('type_name')->values();

        return [
            'summary' => [
                'total' => $rows->count(),
                'new' => $rows->where('action', 'new')->count(),
                'increase' => $rows->where('action', 'increase')->count(),
                'reduce' => $rows->where('action', 'reduce')->count(),
                'replace' => 0,
                'remove' => $rows->where('action', 'remove')->count(),
                'unchanged' => $rows->where('action', 'unchanged')->count(),
            ],
            'rows' => $rows,
            'validation' => [
                'processed_lines' => count($items),
                'ignored_lines' => 0,
                'valid_lines' => count($items),
                'skipped_lines' => count($items) > 0 ? 0 : 1,
                'skipped' => count($items) > 0 ? [] : [[
                    'line' => $doctrineName,
                    'line_number' => null,
                    'reason' => 'No importable items were found in this doctrine.',
                ]],
            ],
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

    private function warningQuantityFromPercentage(int $quantity, int $percentage): int
    {
        $percentage = max(0, min(100, $percentage));

        return max(0, (int) ceil(max(1, $quantity) * ($percentage / 100)));
    }

    private function action(int $currentQuantity, int $newQuantity, bool $exists, string $mode): string
    {
        if (!$exists) {
            return 'new';
        }

        if ($currentQuantity === $newQuantity) {
            return 'unchanged';
        }

        if ($newQuantity <= 0) {
            return 'remove';
        }

        if ($mode === 'doctrine') {
            return $newQuantity > $currentQuantity ? 'increase' : 'reduce';
        }

        if ($mode !== 'add') {
            return $newQuantity > $currentQuantity ? 'replace' : 'reduce';
        }

        return $newQuantity > $currentQuantity ? 'increase' : 'unchanged';
    }
}
