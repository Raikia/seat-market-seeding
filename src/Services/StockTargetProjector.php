<?php

namespace Raikia\SeatMarketSeeding\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Raikia\SeatMarketSeeding\Models\MarketSeedingItemSource;
use Raikia\SeatMarketSeeding\Models\MarketSeedingTrackedDoctrine;
use Raikia\SeatMarketSeeding\Models\SeededMarket;
use Raikia\SeatMarketSeeding\Models\SeededMarketItem;

class StockTargetProjector
{
    private StockTargetQuantity $quantities;

    public function __construct(StockTargetQuantity $quantities)
    {
        $this->quantities = $quantities;
    }

    public function setManualTarget(
        SeededMarket $market,
        int $typeId,
        string $typeName,
        int $quantity,
        ?int $warningQuantity = null,
        ?string $notes = null
    ): SeededMarketItem {
        return DB::transaction(function () use ($market, $typeId, $typeName, $quantity, $warningQuantity, $notes) {
            MarketSeedingItemSource::updateOrCreate([
                'market_id' => $market->id,
                'source_type' => MarketSeedingItemSource::SOURCE_MANUAL,
                'source_key' => 'manual',
                'type_id' => $typeId,
            ], [
                'tracked_doctrine_id' => null,
                'type_name' => $typeName,
                'quantity' => max(1, $quantity),
                'warning_quantity' => $warningQuantity ?: $this->quantities->defaultWarningQuantity(max(1, $quantity)),
            ]);

            $this->recalculateMarket($market);

            $item = $market->items()->where('type_id', $typeId)->firstOrFail();

            if ($notes !== null) {
                $item->notes = $notes;
            }

            $item->save();

            return $item->fresh();
        });
    }

    public function removeManualTarget(SeededMarketItem $item): ?SeededMarketItem
    {
        return DB::transaction(function () use ($item) {
            $market = $item->market;

            MarketSeedingItemSource::query()
                ->where('market_id', $item->market_id)
                ->where('type_id', $item->type_id)
                ->where('source_type', MarketSeedingItemSource::SOURCE_MANUAL)
                ->delete();

            if (!$market) {
                $item->delete();
                return null;
            }

            $this->recalculateMarket($market);

            return $market->items()->where('type_id', $item->type_id)->first();
        });
    }

    public function importManualTargets(SeededMarket $market, array $items, string $mode, bool $keepHigherQuantity = false, int $warningPercentage = 33): int
    {
        return DB::transaction(function () use ($market, $items, $mode, $keepHigherQuantity, $warningPercentage) {
            if ($mode === 'replace') {
                $market->itemSources()
                    ->where('source_type', MarketSeedingItemSource::SOURCE_MANUAL)
                    ->delete();
            }

            $existing = $market->itemSources()
                ->where('source_type', MarketSeedingItemSource::SOURCE_MANUAL)
                ->get()
                ->keyBy('type_id');

            foreach ($items as $item) {
                $typeId = (int) $item['type_id'];
                $source = $existing->get($typeId);
                $currentQuantity = $source ? (int) $source->quantity : 0;
                $importQuantity = (int) $item['quantity'];

                if ($mode === 'add' && $keepHigherQuantity) {
                    $quantity = max($currentQuantity, $importQuantity);
                } elseif ($mode === 'add') {
                    $quantity = $currentQuantity + $importQuantity;
                } else {
                    $quantity = $importQuantity;
                }

                MarketSeedingItemSource::updateOrCreate([
                    'market_id' => $market->id,
                    'source_type' => MarketSeedingItemSource::SOURCE_MANUAL,
                    'source_key' => 'manual',
                    'type_id' => $typeId,
                ], [
                    'tracked_doctrine_id' => null,
                    'type_name' => $item['type_name'],
                    'quantity' => max(1, $quantity),
                    'warning_quantity' => $this->warningQuantityFromPercentage(max(1, $quantity), $warningPercentage),
                ]);
            }

            $this->recalculateMarket($market);

            return count($items);
        });
    }

    public function replaceDoctrineTargets(MarketSeedingTrackedDoctrine $trackedDoctrine, array $items): void
    {
        DB::transaction(function () use ($trackedDoctrine, $items) {
            $typeIds = collect($items)->pluck('type_id')->map(fn ($typeId) => (int) $typeId)->all();

            $trackedDoctrine->sources()
                ->whereNotIn('type_id', $typeIds ?: [0])
                ->delete();

            foreach ($items as $item) {
                MarketSeedingItemSource::updateOrCreate([
                    'market_id' => $trackedDoctrine->market_id,
                    'source_type' => MarketSeedingItemSource::SOURCE_DOCTRINE,
                    'source_key' => 'seat-fitting-doctrine:' . $trackedDoctrine->doctrine_id,
                    'type_id' => (int) $item['type_id'],
                ], [
                    'tracked_doctrine_id' => $trackedDoctrine->id,
                    'type_name' => $item['type_name'],
                    'quantity' => max(1, (int) $item['quantity']),
                    'warning_quantity' => $this->warningQuantityForDoctrineSource($trackedDoctrine, max(1, (int) $item['quantity'])),
                ]);
            }

            $this->recalculateMarket($trackedDoctrine->market);
        });
    }

    public function recalculateMarket(SeededMarket $market): void
    {
        $sources = $market->itemSources()
            ->with('trackedDoctrine')
            ->get()
            ->groupBy('type_id');
        $existing = $market->items()->get()->keyBy('type_id');
        $effectiveTypeIds = [];

        foreach ($sources as $typeId => $typeSources) {
            $projection = $this->projectionForSources($typeSources);

            if ($projection['quantity'] < 1) {
                continue;
            }

            $item = $existing->get((int) $typeId) ?: new SeededMarketItem([
                'market_id' => $market->id,
                'type_id' => (int) $typeId,
            ]);

            $item->type_name = $projection['type_name'];
            $item->desired_quantity = $projection['quantity'];
            $item->warning_quantity = $projection['warning_quantity'];
            $item->save();

            $effectiveTypeIds[] = (int) $typeId;

            $market->itemSources()
                ->where('type_id', (int) $typeId)
                ->update(['item_id' => $item->id]);
        }

        $itemsToDelete = $market->items();

        if ($effectiveTypeIds) {
            $itemsToDelete->whereNotIn('type_id', $effectiveTypeIds);
        }

        $itemsToDelete->delete();

        $market->itemSources()
            ->whereNotIn('type_id', $effectiveTypeIds ?: [0])
            ->update(['item_id' => null]);
    }

    private function projectionForSources(Collection $sources): array
    {
        $manualQuantity = (int) $sources
            ->where('source_type', MarketSeedingItemSource::SOURCE_MANUAL)
            ->sum('quantity');
        $manualWarningQuantity = (int) $sources
            ->where('source_type', MarketSeedingItemSource::SOURCE_MANUAL)
            ->sum('warning_quantity');
        $addQuantity = 0;
        $addWarningQuantity = 0;
        $maxQuantity = 0;
        $maxWarningQuantity = 0;
        $typeName = optional($sources->first())->type_name;

        $sources
            ->where('source_type', MarketSeedingItemSource::SOURCE_DOCTRINE)
            ->each(function (MarketSeedingItemSource $source) use (&$addQuantity, &$addWarningQuantity, &$maxQuantity, &$maxWarningQuantity, &$typeName) {
                $typeName = $source->type_name ?: $typeName;
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

        if ($manualWarningQuantity < 1 && $manualQuantity > 0) {
            $manualWarningQuantity = $this->quantities->defaultWarningQuantity($manualQuantity);
        }

        $baseWarningQuantity = $manualQuantity >= $maxQuantity
            ? $manualWarningQuantity
            : $maxWarningQuantity;
        $quantity = max($manualQuantity, $maxQuantity) + $addQuantity;

        return [
            'type_name' => $typeName,
            'quantity' => $quantity,
            'warning_quantity' => max(1, $baseWarningQuantity + $addWarningQuantity),
        ];
    }

    private function warningQuantityForDoctrineSource(MarketSeedingTrackedDoctrine $trackedDoctrine, int $quantity): int
    {
        return $this->warningQuantityFromPercentage($quantity, (int) ($trackedDoctrine->warning_percentage ?: 33));
    }

    private function warningQuantityFromPercentage(int $quantity, int $percentage): int
    {
        $percentage = max(1, min(100, $percentage));

        return max(1, (int) ceil($quantity * ($percentage / 100)));
    }
}
