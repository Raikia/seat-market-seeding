<?php

namespace Raikia\SeatMarketSeeding\Services;

use Raikia\SeatMarketSeeding\Helpers\SeatFittingPluginHelper;
use Raikia\SeatMarketSeeding\Models\MarketSeedingTrackedDoctrine;
use Raikia\SeatMarketSeeding\Models\SeededMarket;

class DoctrineTrackingSync
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
        return SeatFittingPluginHelper::pluginIsAvailable();
    }

    public function syncMarket(SeededMarket $market): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }

        $market->loadMissing('trackedDoctrines');
        $synced = 0;

        foreach ($market->trackedDoctrines as $trackedDoctrine) {
            $this->syncDoctrine($trackedDoctrine);
            $synced++;
        }

        return $synced;
    }

    public function syncDoctrine(MarketSeedingTrackedDoctrine $trackedDoctrine): void
    {
        if (!$this->isAvailable()) {
            $trackedDoctrine->update([
                'last_synced_at' => now(),
                'last_sync_status' => 'skipped',
                'last_sync_message' => 'Seat Fitting is not installed.',
            ]);

            return;
        }

        try {
            $doctrine = SeatFittingPluginHelper::getDoctrineWithFittings($trackedDoctrine->doctrine_id);

            if (!$doctrine) {
                $trackedDoctrine->sources()->delete();
                $this->projector->recalculateMarket($trackedDoctrine->market);
                $trackedDoctrine->update([
                    'last_synced_at' => now(),
                    'last_sync_status' => 'missing',
                    'last_sync_message' => 'Doctrine was not found in Seat Fitting.',
                ]);

                return;
            }

            $trackedDoctrine->doctrine_name = $doctrine->name;
            $trackedDoctrine->save();

            $items = $this->savedFittings->items(
                'seat-fitting-doctrine',
                $trackedDoctrine->doctrine_id,
                $trackedDoctrine->multiplier
            );

            $this->projector->replaceDoctrineTargets($trackedDoctrine, $items);

            $trackedDoctrine->update([
                'last_synced_at' => now(),
                'last_sync_status' => 'success',
                'last_sync_message' => count($items) . ' item type(s) synced.',
            ]);
        } catch (\Throwable $e) {
            $trackedDoctrine->update([
                'last_synced_at' => now(),
                'last_sync_status' => 'error',
                'last_sync_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function doctrineItems(int $doctrineId, int $multiplier): array
    {
        return $this->savedFittings->items('seat-fitting-doctrine', $doctrineId, $multiplier);
    }
}
