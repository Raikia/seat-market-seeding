<?php

namespace Raikia\SeatMarketSeeding\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Raikia\SeatMarketSeeding\Helpers\SeatFittingPluginHelper;
use Raikia\SeatMarketSeeding\Models\Integrations\CharacterFitting;
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

        return $this->seatFittingSources($query)
            ->merge($this->characterFittingSources($query))
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

        return $doctrines->merge($fits);
    }

    private function characterFittingSources(string $query): Collection
    {
        if (!Schema::hasTable('character_fittings')) {
            return collect();
        }

        return CharacterFitting::query()
            ->where('name', 'like', '%' . $this->escapeLike($query) . '%')
            ->orderBy('name')
            ->limit(15)
            ->get()
            ->map(function ($fit) {
                return [
                    'id' => 'character-fit:' . $fit->id,
                    'text' => 'Character Fit: ' . $fit->name,
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

    private function itemsFromCharacterFit(int $id, int $multiplier): array
    {
        $fit = CharacterFitting::with('items.type', 'shipType')->find($id);

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

    private function addType(array &$items, int $typeId, int $quantity, ?InvType $type = null): void
    {
        $type = $type ?: InvType::where('typeID', $typeId)->first();

        if (!$type) {
            return;
        }

        if (!isset($items[$typeId])) {
            $items[$typeId] = [
                'type_id' => $typeId,
                'type_name' => $type->typeName,
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
