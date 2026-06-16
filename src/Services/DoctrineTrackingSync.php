<?php

namespace Raikia\SeatMarketSeeding\Services;

use Raikia\SeatMarketSeeding\Helpers\SeatFittingPluginHelper;
use Raikia\SeatMarketSeeding\Models\MarketSeedingTrackedDoctrine;
use Raikia\SeatMarketSeeding\Models\MarketSeedingTrackedDoctrineFit;
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
            $fitSettings = $this->syncFitSettings($trackedDoctrine);
            $items = $this->savedFittings->doctrineItemsFromFitSettings(
                $trackedDoctrine->doctrine_id,
                $fitSettings,
                $trackedDoctrine->fit_aggregation_mode ?: MarketSeedingTrackedDoctrine::FIT_AGGREGATION_MAX
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

    public function doctrinePreview(int $doctrineId, int $defaultMultiplier, array $submittedSettings, string $aggregationMode): array
    {
        $fitSettings = $this->previewFitSettings($doctrineId, $defaultMultiplier, $submittedSettings);

        return [
            'fits' => $fitSettings,
            'items' => $this->savedFittings->doctrineItemsFromFitSettings($doctrineId, $fitSettings, $aggregationMode),
        ];
    }

    public function syncFitSettings(MarketSeedingTrackedDoctrine $trackedDoctrine, array $submittedSettings = []): array
    {
        $fitSettings = $this->previewFitSettings(
            $trackedDoctrine->doctrine_id,
            $trackedDoctrine->multiplier,
            $submittedSettings,
            $trackedDoctrine->fitSettings()->get()->keyBy('fitting_id')->all()
        );
        $fittingIds = collect($fitSettings)->pluck('fitting_id')->map(fn ($fittingId) => (int) $fittingId)->all();

        $trackedDoctrine->fitSettings()
            ->whereNotIn('fitting_id', $fittingIds ?: [0])
            ->delete();

        foreach ($fitSettings as $fitSetting) {
            $trackedDoctrine->fitSettings()->updateOrCreate([
                'fitting_id' => (int) $fitSetting['fitting_id'],
            ], [
                'fitting_name' => $fitSetting['fitting_name'],
                'ship_type_id' => $fitSetting['ship_type_id'] ?: null,
                'ship_type_name' => $fitSetting['ship_type_name'],
                'ship_multiplier' => max(0, (int) $fitSetting['ship_multiplier']),
                'fitting_multiplier' => max(0, (int) $fitSetting['fitting_multiplier']),
            ]);
        }

        return $fitSettings;
    }

    private function previewFitSettings(int $doctrineId, int $defaultMultiplier, array $submittedSettings = [], array $existingSettings = []): array
    {
        $submitted = collect($submittedSettings)
            ->keyBy(fn ($setting) => (int) ($setting['fitting_id'] ?? 0));
        $existing = collect($existingSettings)
            ->keyBy(fn ($setting) => (int) ($setting instanceof MarketSeedingTrackedDoctrineFit ? $setting->fitting_id : ($setting['fitting_id'] ?? 0)));

        return $this->savedFittings->doctrineFits($doctrineId)
            ->map(function (array $fit) use ($defaultMultiplier, $submitted, $existing) {
                $submittedSetting = $submitted->get((int) $fit['fitting_id']);
                $existingSetting = $existing->get((int) $fit['fitting_id']);

                $fit['ship_multiplier'] = $this->fitMultiplierValue($submittedSetting, $existingSetting, 'ship_multiplier', $defaultMultiplier);
                $fit['fitting_multiplier'] = $this->fitMultiplierValue($submittedSetting, $existingSetting, 'fitting_multiplier', $defaultMultiplier);

                return $fit;
            })
            ->values()
            ->all();
    }

    private function fitMultiplierValue($submittedSetting, $existingSetting, string $field, int $defaultMultiplier): int
    {
        if (is_array($submittedSetting) && array_key_exists($field, $submittedSetting)) {
            return max(0, min(10000, (int) $submittedSetting[$field]));
        }

        if ($existingSetting instanceof MarketSeedingTrackedDoctrineFit) {
            return max(0, min(10000, (int) $existingSetting->{$field}));
        }

        if (is_array($existingSetting) && array_key_exists($field, $existingSetting)) {
            return max(0, min(10000, (int) $existingSetting[$field]));
        }

        return max(0, min(10000, $defaultMultiplier));
    }
}
