<?php

namespace Raikia\SeatMarketSeeding\Services;

use Illuminate\Support\Facades\Schema;
use Raikia\SeatMarketSeeding\Models\MarketSeedingTargetHistory;
use Raikia\SeatMarketSeeding\Models\MarketSeedingTrackedSavedFitting;
use Raikia\SeatMarketSeeding\Models\SeededMarket;

class SavedFittingTrackingSync
{
    private SavedFittingSource $savedFittings;
    private StockTargetProjector $projector;

    public function __construct(SavedFittingSource $savedFittings, StockTargetProjector $projector)
    {
        $this->savedFittings = $savedFittings;
        $this->projector = $projector;
    }

    public function isAvailable(): bool
    {
        return $this->savedFittings->isAvailable()
            && Schema::hasTable('seat_market_seeding_tracked_saved_fittings')
            && Schema::hasColumn('seat_market_seeding_item_sources', 'tracked_saved_fitting_id');
    }

    public function syncMarket(SeededMarket $market): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }

        $market->loadMissing('trackedSavedFittings');
        $synced = 0;

        foreach ($market->trackedSavedFittings as $trackedSavedFitting) {
            $this->syncSavedFitting($trackedSavedFitting);
            $synced++;
        }

        return $synced;
    }

    public function syncSavedFitting(MarketSeedingTrackedSavedFitting $trackedSavedFitting): void
    {
        try {
            $fit = $this->savedFittings->characterFit(
                (int) $trackedSavedFitting->fitting_id,
                (int) $trackedSavedFitting->character_id
            );

            if (!$fit) {
                $market = $trackedSavedFitting->market;
                $trackedSavedFitting->sources()->delete();
                $trackedSavedFitting->delete();

                if ($market) {
                    $this->projector->recalculateMarket($market, MarketSeedingTargetHistory::CHANGE_SAVED_FITTING);
                }

                return;
            }

            $trackedSavedFitting->update([
                'esi_fitting_id' => $fit['esi_fitting_id'] ?: null,
                'fitting_name' => $fit['fitting_name'],
                'ship_type_id' => $fit['ship_type_id'] ?: null,
                'ship_type_name' => $fit['ship_type_name'],
            ]);

            $items = $this->savedFittings->itemsFromFitPayload(
                $fit,
                (int) $trackedSavedFitting->ship_multiplier,
                (int) $trackedSavedFitting->fitting_multiplier
            );

            $this->projector->replaceSavedFittingTargets(
                $trackedSavedFitting->fresh('market'),
                $items,
                MarketSeedingTargetHistory::CHANGE_SAVED_FITTING
            );

            $trackedSavedFitting->update([
                'last_synced_at' => now(),
                'last_sync_status' => 'success',
                'last_sync_message' => count($items) . ' item type(s) synced.',
            ]);
        } catch (\Throwable $e) {
            $trackedSavedFitting->update([
                'last_synced_at' => now(),
                'last_sync_status' => 'error',
                'last_sync_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function previewSavedFitting(int $fittingId, int $shipMultiplier, int $fittingMultiplier, ?int $characterId = null): ?array
    {
        $fit = $this->savedFittings->characterFit($fittingId, $characterId);

        if (!$fit) {
            return null;
        }

        $fit['ship_multiplier'] = max(0, min(10000, $shipMultiplier));
        $fit['fitting_multiplier'] = max(0, min(10000, $fittingMultiplier));

        return [
            'fit' => $fit,
            'items' => $this->savedFittings->itemsFromFitPayload(
                $fit,
                (int) $fit['ship_multiplier'],
                (int) $fit['fitting_multiplier']
            ),
        ];
    }
}
