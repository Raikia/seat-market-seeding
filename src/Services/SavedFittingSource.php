<?php

namespace Raikia\SeatMarketSeeding\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Raikia\SeatMarketSeeding\Helpers\SeatFittingPluginHelper;
use Raikia\SeatMarketSeeding\Models\Integrations\CharacterFitting;
use Raikia\SeatMarketSeeding\Models\MarketSeedingTrackedDoctrine;
use Seat\Eveapi\Models\Sde\InvType;

class SavedFittingSource
{
    public function isAvailable(): bool
    {
        return Schema::hasTable('character_fittings')
            || SeatFittingPluginHelper::pluginIsAvailable();
    }

    public function search(string $query): array
    {
        $query = trim($query);

        if (strlen($query) < 2) {
            return [];
        }

        return collect($this->seatFittingSources($query)->all())
            ->merge(collect($this->characterFittingSources($query)->all()))
            ->sortBy('text')
            ->values()
            ->all();
    }

    public function searchDoctrines(string $query): array
    {
        $query = trim($query);

        if (strlen($query) < 2 || !SeatFittingPluginHelper::pluginIsAvailable()) {
            return [];
        }

        return SeatFittingPluginHelper::searchDoctrines($query)
            ->map(function ($doctrine) {
                return [
                    'id' => $doctrine->id,
                    'text' => $doctrine->name,
                ];
            })
            ->values()
            ->all();
    }

    public function items(string $source, int $id, int $multiplier = 1): array
    {
        $multiplier = max(1, $multiplier);

        if ($source === 'seat-fitting-doctrine' && SeatFittingPluginHelper::pluginIsAvailable()) {
            return $this->itemsFromSeatFittingDoctrine($id, $multiplier);
        }

        if ($source === 'seat-fitting-fit' && SeatFittingPluginHelper::pluginIsAvailable()) {
            return $this->itemsFromSeatFittingFit($id, $multiplier);
        }

        if ($source === 'character-fit' && Schema::hasTable('character_fittings')) {
            return $this->itemsFromCharacterFit($id, $multiplier);
        }

        return [];
    }

    public function doctrineFits(int $doctrineId): Collection
    {
        if (!SeatFittingPluginHelper::pluginIsAvailable()) {
            return collect();
        }

        $doctrine = SeatFittingPluginHelper::getDoctrineWithFittings($doctrineId);

        if (!$doctrine) {
            return collect();
        }

        return $doctrine->fittings
            ->map(function ($fit) {
                return $this->doctrineFitPayload($fit);
            })
            ->filter()
            ->values();
    }

    public function doctrineItemsFromFitSettings(int $doctrineId, array $fitSettings, string $aggregationMode): array
    {
        $fits = $this->doctrineFits($doctrineId);

        if ($fits->isEmpty()) {
            return [];
        }

        $settings = collect($fitSettings)
            ->keyBy(fn ($setting) => (int) ($setting['fitting_id'] ?? 0));
        $items = [];

        foreach ($fits as $fit) {
            $fitContribution = [];
            $setting = $settings->get((int) $fit['fitting_id'], []);
            $shipMultiplier = max(0, (int) ($setting['ship_multiplier'] ?? 1));
            $fittingMultiplier = max(0, (int) ($setting['fitting_multiplier'] ?? 1));

            if ($shipMultiplier > 0 && !empty($fit['ship_type_id'])) {
                $this->addType($fitContribution, (int) $fit['ship_type_id'], $shipMultiplier, null, $fit['ship_type_name']);
            }

            foreach ($fit['items'] as $item) {
                if ($fittingMultiplier < 1) {
                    continue;
                }

                $this->addType(
                    $fitContribution,
                    (int) $item['type_id'],
                    (int) $item['quantity'] * $fittingMultiplier,
                    null,
                    $item['type_name']
                );
            }

            if ($aggregationMode === MarketSeedingTrackedDoctrine::FIT_AGGREGATION_MAX) {
                foreach ($fitContribution as $typeId => $item) {
                    if (!isset($items[$typeId]) || (int) $item['quantity'] > (int) $items[$typeId]['quantity']) {
                        $items[$typeId] = $item;
                    }
                }

                continue;
            }

            foreach ($fitContribution as $typeId => $item) {
                $this->addType($items, (int) $typeId, (int) $item['quantity'], null, $item['type_name']);
            }
        }

        return collect($items)
            ->filter(fn ($item) => (int) $item['quantity'] > 0)
            ->sortBy('type_name')
            ->values()
            ->all();
    }

    private function seatFittingSources(string $query): Collection
    {
        if (!SeatFittingPluginHelper::pluginIsAvailable()) {
            return collect();
        }

        $doctrines = SeatFittingPluginHelper::searchDoctrines($query)
            ->map(function ($doctrine) {
                return [
                    'id' => 'seat-fitting-doctrine:' . $doctrine->id,
                    'text' => 'Doctrine: ' . $doctrine->name,
                    'source' => 'seat-fitting-doctrine',
                    'source_id' => $doctrine->id,
                ];
            });

        $fits = SeatFittingPluginHelper::searchFittings($query)
            ->map(function ($fit) {
                return [
                    'id' => 'seat-fitting-fit:' . $fit->fitting_id,
                    'text' => 'Fit: ' . $fit->name,
                    'source' => 'seat-fitting-fit',
                    'source_id' => $fit->fitting_id,
                ];
            });

        return collect($doctrines->all())
            ->merge(collect($fits->all()));
    }

    private function characterFittingSources(string $query): Collection
    {
        if (!Schema::hasTable('character_fittings')) {
            return collect();
        }

        $characterIds = $this->currentUserCharacterIds();

        if ($characterIds->isEmpty()) {
            return collect();
        }

        return CharacterFitting::query()
            ->with('characterName')
            ->whereIn('character_id', $characterIds)
            ->where('name', 'like', '%' . $this->escapeLike($query) . '%')
            ->orderBy('name')
            ->limit(15)
            ->get()
            ->map(function ($fit) {
                $characterName = optional($fit->characterName)->name ?: 'Unknown Character';

                return [
                    'id' => 'character-fit:' . $fit->id,
                    'text' => 'Character Fit: ' . $fit->name . ' (' . $characterName . ')',
                    'source' => 'character-fit',
                    'source_id' => $fit->id,
                ];
            });
    }

    private function itemsFromSeatFittingDoctrine(int $doctrineId, int $multiplier): array
    {
        $doctrine = SeatFittingPluginHelper::getDoctrineWithFittings($doctrineId);

        return $doctrine
            ? $this->itemsFromSeatFittings($doctrine->fittings, $multiplier)
            : [];
    }

    private function itemsFromSeatFittingFit(int $fittingId, int $multiplier): array
    {
        $fit = SeatFittingPluginHelper::getFittingWithItems($fittingId);

        return $fit
            ? $this->itemsFromSeatFittings(collect([$fit]), $multiplier)
            : [];
    }

    private function itemsFromSeatFittings(Collection $fits, int $multiplier): array
    {
        $items = [];

        $fits->each(function ($fit) use (&$items, $multiplier) {
            $this->addType($items, (int) $fit->ship_type_id, $multiplier, $fit->ship);

            $fit->items->each(function ($item) use (&$items, $multiplier) {
                $this->addType($items, (int) $item->type_id, (int) $item->quantity * $multiplier, $item->type);
            });
        });

        return array_values($items);
    }

    private function doctrineFitPayload($fit): ?array
    {
        $fittingId = (int) ($fit->fitting_id ?? $fit->id ?? 0);

        if (!$fittingId) {
            return null;
        }

        return [
            'fitting_id' => $fittingId,
            'fitting_name' => $fit->name ?: 'Unnamed Fit',
            'ship_type_id' => (int) $fit->ship_type_id,
            'ship_type_name' => optional($fit->ship)->typeName ?: 'Unknown Ship',
            'items' => $fit->items
                ->map(function ($item) {
                    $type = $item->type ?: InvType::where('typeID', (int) $item->type_id)->first();

                    if (!$type) {
                        return null;
                    }

                    return [
                        'type_id' => (int) $item->type_id,
                        'type_name' => $type->typeName,
                        'quantity' => max(1, (int) $item->quantity),
                        'flag' => (int) ($item->flag ?? 0),
                        'slot_group' => $this->slotGroup((int) ($item->flag ?? 0)),
                    ];
                })
                ->filter()
                ->values()
                ->all(),
        ];
    }

    private function itemsFromCharacterFit(int $id, int $multiplier): array
    {
        $characterIds = $this->currentUserCharacterIds();

        if ($characterIds->isEmpty()) {
            return [];
        }

        $fit = CharacterFitting::with('items.type', 'shipType')
            ->whereIn('character_id', $characterIds)
            ->find($id);

        if (!$fit) {
            return [];
        }

        $items = [];
        $this->addType($items, (int) $fit->ship_type_id, $multiplier, $fit->shipType);

        $fit->items->each(function ($item) use (&$items, $multiplier) {
            $this->addType($items, (int) $item->type_id, (int) $item->quantity * $multiplier, $item->type);
        });

        return array_values($items);
    }

    private function currentUserCharacterIds(): Collection
    {
        $user = auth()->user();

        if (!$user) {
            return collect();
        }

        $characterIds = method_exists($user, 'associatedCharacterIds')
            ? collect($user->associatedCharacterIds())
            : collect();

        if ($characterIds->isEmpty() && $user->main_character_id) {
            $characterIds->push($user->main_character_id);
        }

        if ($characterIds->isEmpty() && method_exists($user, 'refresh_tokens')) {
            $characterIds = $user->refresh_tokens()->pluck('character_id');
        }

        return $characterIds
            ->map(fn ($characterId) => (int) $characterId)
            ->filter()
            ->unique()
            ->values();
    }

    private function slotGroup(int $flag): string
    {
        if ($flag >= 27 && $flag <= 34) {
            return 'High Slots';
        }

        if ($flag >= 19 && $flag <= 26) {
            return 'Medium Slots';
        }

        if ($flag >= 11 && $flag <= 18) {
            return 'Low Slots';
        }

        if ($flag >= 92 && $flag <= 99) {
            return 'Rigs';
        }

        if ($flag === 87) {
            return 'Drone Bay';
        }

        if (in_array($flag, [5, 133], true)) {
            return 'Cargo';
        }

        if ($flag >= 164 && $flag <= 171) {
            return 'Service Slots';
        }

        return 'Other';
    }

    private function addType(array &$items, int $typeId, int $quantity, ?InvType $type = null, ?string $typeName = null): void
    {
        $type = $type ?: ($typeName ? null : InvType::where('typeID', $typeId)->first());

        if (!$type && !$typeName) {
            return;
        }

        if (!isset($items[$typeId])) {
            $items[$typeId] = [
                'type_id' => $typeId,
                'type_name' => $typeName ?: $type->typeName,
                'quantity' => 0,
            ];
        }

        $items[$typeId]['quantity'] += max(1, $quantity);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
