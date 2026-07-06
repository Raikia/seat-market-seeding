<?php

namespace Raikia\SeatMarketSeeding\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Raikia\SeatMarketSeeding\Models\MarketSeedingItemSource;
use Raikia\SeatMarketSeeding\Models\MarketSeedingTargetHistory;
use Raikia\SeatMarketSeeding\Models\MarketSeedingTrackedDoctrine;
use Raikia\SeatMarketSeeding\Models\MarketSeedingTrackedSavedFitting;
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
        ?string $notes = null,
        string $changeType = MarketSeedingTargetHistory::CHANGE_MANUAL
    ): SeededMarketItem {
        return DB::transaction(function () use ($market, $typeId, $typeName, $quantity, $warningQuantity, $notes, $changeType) {
            MarketSeedingItemSource::updateOrCreate([
                'market_id' => $market->id,
                'source_type' => MarketSeedingItemSource::SOURCE_MANUAL,
                'source_key' => 'manual',
                'type_id' => $typeId,
            ], $this->sourceValues([
                'tracked_doctrine_id' => null,
                'tracked_saved_fitting_id' => null,
                'type_name' => $typeName,
                'quantity' => max(1, $quantity),
                'warning_quantity' => $this->normalizeWarningQuantity($warningQuantity, max(1, $quantity)),
            ]));

            $this->recalculateMarket($market, $changeType);

            $item = $market->items()->where('type_id', $typeId)->firstOrFail();

            if ($notes !== null) {
                $item->notes = $notes;
            }

            $item->save();

            return $item->fresh();
        }, 5);
    }

    public function setEffectiveTarget(
        SeededMarketItem $item,
        int $desiredQuantity,
        ?int $warningQuantity = null,
        ?string $notes = null,
        string $changeType = MarketSeedingTargetHistory::CHANGE_MANUAL
    ): SeededMarketItem
    {
        return DB::transaction(function () use ($item, $desiredQuantity, $warningQuantity, $notes, $changeType) {
            $market = $item->market;
            $sources = $market->itemSources()
                ->with($this->sourceRelations())
                ->where('type_id', $item->type_id)
                ->get();
            $hasLinkedSources = $sources
                ->whereIn('source_type', [MarketSeedingItemSource::SOURCE_DOCTRINE, MarketSeedingItemSource::SOURCE_SAVED_FIT])
                ->isNotEmpty();
            $desiredQuantity = max(1, $desiredQuantity);
            $baseProjection = $this->projectSources($sources, null, null, false);
            $adjustmentQuantity = max(0, $desiredQuantity - $baseProjection['quantity']);
            $adjustmentWarningQuantity = $this->normalizeWarningQuantity($warningQuantity, $desiredQuantity);

            if (!$hasLinkedSources) {
                MarketSeedingItemSource::updateOrCreate([
                    'market_id' => $market->id,
                    'source_type' => MarketSeedingItemSource::SOURCE_MANUAL,
                    'source_key' => 'manual',
                    'type_id' => $item->type_id,
                ], $this->sourceValues([
                    'tracked_doctrine_id' => null,
                    'tracked_saved_fitting_id' => null,
                    'type_name' => $item->type_name,
                    'quantity' => $desiredQuantity,
                    'warning_quantity' => $adjustmentWarningQuantity,
                ]));

                $market->itemSources()
                    ->where('type_id', $item->type_id)
                    ->where('source_type', MarketSeedingItemSource::SOURCE_MANUAL_ADJUSTMENT)
                    ->delete();

                $this->recalculateMarket($market, $changeType);

                $item = $market->items()->where('type_id', $item->type_id)->firstOrFail();

                if ($notes !== null) {
                    $item->notes = $notes;
                    $item->save();
                }

                return $item->fresh();
            }

            if ($adjustmentQuantity < 1) {
                $market->itemSources()
                    ->where('type_id', $item->type_id)
                    ->where('source_type', MarketSeedingItemSource::SOURCE_MANUAL_ADJUSTMENT)
                    ->delete();

                $this->recalculateMarket($market, $changeType);

                return $market->items()->where('type_id', $item->type_id)->firstOrFail()->fresh();
            }

            MarketSeedingItemSource::updateOrCreate([
                'market_id' => $market->id,
                'source_type' => MarketSeedingItemSource::SOURCE_MANUAL_ADJUSTMENT,
                'source_key' => 'inline-adjustment',
                'type_id' => $item->type_id,
            ], $this->sourceValues([
                'tracked_doctrine_id' => null,
                'tracked_saved_fitting_id' => null,
                'type_name' => $item->type_name,
                'quantity' => $adjustmentQuantity,
                'warning_quantity' => $adjustmentWarningQuantity,
            ]));

            $this->recalculateMarket($market, $changeType);

            $item = $market->items()->where('type_id', $item->type_id)->firstOrFail();

            if ($notes !== null) {
                $item->notes = $notes;
                $item->save();
            }

            return $item->fresh();
        }, 5);
    }

    public function removeManualTarget(SeededMarketItem $item): ?SeededMarketItem
    {
        return DB::transaction(function () use ($item) {
            $market = $item->market;

            MarketSeedingItemSource::query()
                ->where('market_id', $item->market_id)
                ->where('type_id', $item->type_id)
                ->whereIn('source_type', [MarketSeedingItemSource::SOURCE_MANUAL, MarketSeedingItemSource::SOURCE_MANUAL_ADJUSTMENT])
                ->delete();

            if (!$market) {
                $item->delete();
                return null;
            }

            $this->recalculateMarket($market, MarketSeedingTargetHistory::CHANGE_MANUAL);

            return $market->items()->where('type_id', $item->type_id)->first();
        }, 5);
    }

    public function importManualTargets(
        SeededMarket $market,
        array $items,
        string $mode,
        bool $keepHigherQuantity = false,
        int $warningPercentage = 33,
        string $changeType = MarketSeedingTargetHistory::CHANGE_BULK_IMPORT
    ): int
    {
        return DB::transaction(function () use ($market, $items, $mode, $keepHigherQuantity, $warningPercentage, $changeType) {
            if ($mode === 'replace') {
                $market->itemSources()
                    ->whereIn('source_type', [MarketSeedingItemSource::SOURCE_MANUAL, MarketSeedingItemSource::SOURCE_MANUAL_ADJUSTMENT])
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
                ], $this->sourceValues([
                    'tracked_doctrine_id' => null,
                    'tracked_saved_fitting_id' => null,
                    'type_name' => $item['type_name'],
                    'quantity' => max(1, $quantity),
                    'warning_quantity' => $this->quantities->warningQuantityFromPercentage(max(1, $quantity), $warningPercentage),
                ]));
            }

            $this->recalculateMarket($market, $changeType);

            return count($items);
        }, 5);
    }

    public function replaceDoctrineTargets(
        MarketSeedingTrackedDoctrine $trackedDoctrine,
        array $items,
        string $changeType = MarketSeedingTargetHistory::CHANGE_DOCTRINE
    ): void
    {
        DB::transaction(function () use ($trackedDoctrine, $items, $changeType) {
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
                ], $this->sourceValues([
                    'tracked_doctrine_id' => $trackedDoctrine->id,
                    'tracked_saved_fitting_id' => null,
                    'type_name' => $item['type_name'],
                    'quantity' => max(1, (int) $item['quantity']),
                    'warning_quantity' => $this->warningQuantityForDoctrineSource($trackedDoctrine, max(1, (int) $item['quantity'])),
                ]));
            }

            $this->recalculateMarket($trackedDoctrine->market, $changeType);
        }, 5);
    }

    public function replaceSavedFittingTargets(
        MarketSeedingTrackedSavedFitting $trackedSavedFitting,
        array $items,
        string $changeType = MarketSeedingTargetHistory::CHANGE_SAVED_FITTING
    ): void
    {
        if (!$this->trackedSavedFittingAvailable()) {
            throw new \RuntimeException('Saved fit tracking is not available until the market seeding migrations have run.');
        }

        DB::transaction(function () use ($trackedSavedFitting, $items, $changeType) {
            $typeIds = collect($items)->pluck('type_id')->map(fn ($typeId) => (int) $typeId)->all();

            $trackedSavedFitting->sources()
                ->whereNotIn('type_id', $typeIds ?: [0])
                ->delete();

            foreach ($items as $item) {
                MarketSeedingItemSource::updateOrCreate([
                    'market_id' => $trackedSavedFitting->market_id,
                    'source_type' => MarketSeedingItemSource::SOURCE_SAVED_FIT,
                    'source_key' => 'character-fit:' . $trackedSavedFitting->character_id . ':' . $trackedSavedFitting->fitting_id,
                    'type_id' => (int) $item['type_id'],
                ], $this->sourceValues([
                    'tracked_doctrine_id' => null,
                    'tracked_saved_fitting_id' => $trackedSavedFitting->id,
                    'type_name' => $item['type_name'],
                    'quantity' => max(1, (int) $item['quantity']),
                    'warning_quantity' => $this->warningQuantityForSavedFittingSource($trackedSavedFitting, max(1, (int) $item['quantity'])),
                ]));
            }

            $this->recalculateMarket($trackedSavedFitting->market, $changeType);
        }, 5);
    }

    public function recalculateMarket(SeededMarket $market, string $changeType = MarketSeedingTargetHistory::CHANGE_SYSTEM): void
    {
        $sources = $market->itemSources()
            ->with($this->sourceRelations())
            ->get()
            ->groupBy('type_id');
        $existing = $market->items()->get()->keyBy('type_id');
        $effectiveTypeIds = [];

        foreach ($sources as $typeId => $typeSources) {
            $projection = $this->projectSources($typeSources);

            if ($projection['quantity'] < 1) {
                continue;
            }

            $item = $existing->get((int) $typeId) ?: new SeededMarketItem([
                'market_id' => $market->id,
                'type_id' => (int) $typeId,
            ]);
            $oldTargetQuantity = $item->exists ? (int) $item->desired_quantity : null;
            $oldWarningQuantity = $item->exists ? (int) $item->warning_quantity : null;

            $item->type_name = $projection['type_name'];
            $item->desired_quantity = $projection['quantity'];
            $item->warning_quantity = $projection['warning_quantity'];
            $item->save();

            $this->recordTargetHistoryIfChanged(
                $market,
                $item,
                $oldTargetQuantity,
                (int) $item->desired_quantity,
                $oldWarningQuantity,
                (int) $item->warning_quantity,
                $changeType
            );

            $effectiveTypeIds[] = (int) $typeId;

            $market->itemSources()
                ->where('type_id', (int) $typeId)
                ->update(['item_id' => $item->id]);
        }

        $itemsToDelete = $market->items();

        if ($effectiveTypeIds) {
            $itemsToDelete->whereNotIn('type_id', $effectiveTypeIds);
        }

        $itemsToDelete->get()->each(function (SeededMarketItem $item) use ($market, $changeType) {
            $this->recordTargetHistoryIfChanged(
                $market,
                $item,
                (int) $item->desired_quantity,
                0,
                (int) $item->warning_quantity,
                0,
                $changeType
            );

            $item->delete();
        });

        $market->itemSources()
            ->whereNotIn('type_id', $effectiveTypeIds ?: [0])
            ->update(['item_id' => null]);
    }

    public function projectSources(Collection $sources, ?int $manualQuantityOverride = null, ?int $manualWarningQuantityOverride = null, bool $includeAdjustments = true): array
    {
        $manualSources = $sources->where('source_type', MarketSeedingItemSource::SOURCE_MANUAL);
        $manualQuantity = $manualQuantityOverride ?? (int) $manualSources->sum('quantity');
        $manualWarningQuantity = $manualWarningQuantityOverride ?? (int) $manualSources->sum('warning_quantity');
        $adjustmentSources = $includeAdjustments
            ? $sources->where('source_type', MarketSeedingItemSource::SOURCE_MANUAL_ADJUSTMENT)
            : collect();
        $adjustmentQuantity = (int) $adjustmentSources->sum('quantity');
        $adjustmentWarningQuantity = (int) $adjustmentSources->sum('warning_quantity');
        $typeName = optional($sources->first())->type_name;
        $linkedProjection = $this->linkedProjection($sources);

        if ($manualWarningQuantity < 1 && $manualQuantity > 0 && $manualSources->every(fn ($source) => $source->warning_quantity === null)) {
            $manualWarningQuantity = $manualWarningQuantityOverride === null
                ? $this->quantities->defaultWarningQuantity($manualQuantity)
                : $manualWarningQuantity;
        }

        $baseWarningQuantity = $manualQuantity >= $linkedProjection['max_quantity']
            ? $manualWarningQuantity
            : $linkedProjection['max_warning_quantity'];
        $quantity = max($manualQuantity, $linkedProjection['max_quantity'])
            + $linkedProjection['add_quantity']
            + $adjustmentQuantity;

        $warningQuantity = $adjustmentSources->isNotEmpty()
            ? $adjustmentWarningQuantity
            : $baseWarningQuantity + $linkedProjection['add_warning_quantity'];

        return [
            'type_name' => $typeName,
            'quantity' => $quantity,
            'warning_quantity' => $this->quantities->clampWarningQuantity($warningQuantity, $quantity),
        ];
    }

    public function doctrineProjection(Collection $sources): array
    {
        return $this->linkedProjection($sources);
    }

    public function linkedProjection(Collection $sources): array
    {
        $addQuantity = 0;
        $addWarningQuantity = 0;
        $maxQuantity = 0;
        $maxWarningQuantity = 0;

        $sources
            ->whereIn('source_type', [MarketSeedingItemSource::SOURCE_DOCTRINE, MarketSeedingItemSource::SOURCE_SAVED_FIT])
            ->each(function (MarketSeedingItemSource $source) use (&$addQuantity, &$addWarningQuantity, &$maxQuantity, &$maxWarningQuantity) {
                $mergeMode = $source->source_type === MarketSeedingItemSource::SOURCE_SAVED_FIT
                    ? (optional($source->trackedSavedFitting)->merge_mode ?: MarketSeedingTrackedSavedFitting::MERGE_MAX)
                    : (optional($source->trackedDoctrine)->merge_mode ?: MarketSeedingTrackedDoctrine::MERGE_MAX);
                $quantity = (int) $source->quantity;
                $warningQuantity = $source->warning_quantity ?? $this->quantities->defaultWarningQuantity($quantity);

                if (in_array($mergeMode, [MarketSeedingTrackedDoctrine::MERGE_ADD, MarketSeedingTrackedSavedFitting::MERGE_ADD], true)) {
                    $addQuantity += $quantity;
                    $addWarningQuantity += (int) $warningQuantity;
                    return;
                }

                if ($quantity > $maxQuantity) {
                    $maxQuantity = $quantity;
                    $maxWarningQuantity = (int) $warningQuantity;
                } elseif ($quantity === $maxQuantity) {
                    $maxWarningQuantity = max($maxWarningQuantity, (int) $warningQuantity);
                }
            });

        return [
            'add_quantity' => $addQuantity,
            'add_warning_quantity' => $addWarningQuantity,
            'max_quantity' => $maxQuantity,
            'max_warning_quantity' => $maxWarningQuantity,
        ];
    }

    private function warningQuantityForDoctrineSource(MarketSeedingTrackedDoctrine $trackedDoctrine, int $quantity): int
    {
        return $this->quantities->warningQuantityFromPercentage($quantity, (int) ($trackedDoctrine->warning_percentage ?? 33));
    }

    private function warningQuantityForSavedFittingSource(MarketSeedingTrackedSavedFitting $trackedSavedFitting, int $quantity): int
    {
        return $this->quantities->warningQuantityFromPercentage($quantity, (int) ($trackedSavedFitting->warning_percentage ?? 33));
    }

    private function warningQuantityFromPercentage(int $quantity, int $percentage): int
    {
        return $this->quantities->warningQuantityFromPercentage($quantity, $percentage);
    }

    private function normalizeWarningQuantity(?int $warningQuantity, int $desiredQuantity): int
    {
        if ($warningQuantity === null) {
            return $this->quantities->defaultWarningQuantity($desiredQuantity);
        }

        return $this->quantities->clampWarningQuantity($warningQuantity, $desiredQuantity);
    }

    private function recordTargetHistoryIfChanged(
        SeededMarket $market,
        SeededMarketItem $item,
        ?int $oldTargetQuantity,
        ?int $newTargetQuantity,
        ?int $oldWarningQuantity,
        ?int $newWarningQuantity,
        string $changeType
    ): void {
        if ($oldTargetQuantity === $newTargetQuantity && $oldWarningQuantity === $newWarningQuantity) {
            return;
        }

        $user = auth()->user();

        MarketSeedingTargetHistory::create([
            'market_id' => $market->id,
            'item_id' => $item->exists ? $item->id : null,
            'type_id' => $item->type_id,
            'market_name' => $market->name,
            'location_name' => $market->location_name,
            'type_name' => $item->type_name,
            'old_target_quantity' => $oldTargetQuantity,
            'new_target_quantity' => $newTargetQuantity,
            'old_warning_quantity' => $oldWarningQuantity,
            'new_warning_quantity' => $newWarningQuantity,
            'change_type' => $changeType,
            'user_id' => optional($user)->id,
            'user_name' => $this->actorName($user),
        ]);
    }

    private function actorName($user): string
    {
        if (!$user) {
            return 'System';
        }

        return $user->name
            ?? $user->username
            ?? optional($user->main_character)->name
            ?? ('User #' . $user->id);
    }

    private function sourceRelations(): array
    {
        $relations = ['trackedDoctrine'];

        if ($this->trackedSavedFittingAvailable()) {
            $relations[] = 'trackedSavedFitting';
        }

        return $relations;
    }

    private function sourceValues(array $values): array
    {
        if (!$this->trackedSavedFittingAvailable()) {
            unset($values['tracked_saved_fitting_id']);
        }

        return $values;
    }

    private function trackedSavedFittingAvailable(): bool
    {
        return Schema::hasTable('seat_market_seeding_tracked_saved_fittings')
            && Schema::hasColumn('seat_market_seeding_item_sources', 'tracked_saved_fitting_id');
    }
}
