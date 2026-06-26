<?php

namespace Raikia\SeatMarketSeeding\Services;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;
use Raikia\SeatMarketSeeding\Models\SeededMarket;
use Raikia\SeatMarketSeeding\Models\SeededMarketItem;
use Seat\Eveapi\Models\Market\MarketOrder;
use Seat\Eveapi\Models\Market\Price;
use Seat\Eveapi\Models\Sde\InvType;

class MarketStockReport
{
    const JITA_STATION_ID = 60003760;

    const SHIP_CATEGORY_ID = 6;

    const SHIP_PACKAGED_VOLUMES = [
        'Capsule' => 500,
        'Shuttle' => 500,
        'Corvette' => 2500,
        'Frigate' => 2500,
        'Assault Frigate' => 2500,
        'Covert Ops' => 2500,
        'Electronic Attack Ship' => 2500,
        'Expedition Frigate' => 2500,
        'Interceptor' => 2500,
        'Logistics Frigate' => 2500,
        'Prototype Exploration Ship' => 2500,
        'Stealth Bomber' => 2500,
        'Destroyer' => 5000,
        'Command Destroyer' => 5000,
        'Interdictor' => 5000,
        'Tactical Destroyer' => 5000,
        'Cruiser' => 10000,
        'Combat Recon Ship' => 10000,
        'Flag Cruiser' => 10000,
        'Force Recon Ship' => 10000,
        'Heavy Assault Cruiser' => 10000,
        'Heavy Interdiction Cruiser' => 10000,
        'Logistics' => 10000,
        'Strategic Cruiser' => 10000,
        'Attack Battlecruiser' => 15000,
        'Combat Battlecruiser' => 15000,
        'Command Ship' => 15000,
        'Battleship' => 50000,
        'Black Ops' => 50000,
        'Marauder' => 50000,
        'Hauler' => 20000,
        'Blockade Runner' => 20000,
        'Deep Space Transport' => 20000,
        'Mining Barge' => 3750,
        'Exhumer' => 3750,
        'Carrier' => 1300000,
        'Capital Industrial Ship' => 1300000,
        'Dreadnought' => 1300000,
        'Force Auxiliary' => 1300000,
        'Freighter' => 1300000,
        'Jump Freighter' => 1300000,
        'Lancer Dreadnought' => 1300000,
        'Supercarrier' => 10000000,
        'Titan' => 10000000,
    ];

    const TYPE_PACKAGED_VOLUME_OVERRIDES = [
        'Orca' => 500000,
        'Porpoise' => 5000,
    ];

    public function build(Collection $markets): array
    {
        $this->loadMarketRelations($markets);

        $typeIds = $markets->flatMap(function (SeededMarket $market) {
            return $market->items->pluck('type_id');
        })->unique()->values();

        $locationIds = $markets->pluck('location_id')->unique()->values();

        $localOrders = $this->localSellOrders($locationIds, $typeIds);
        $jitaPrices = $this->jitaSellPrices($typeIds);
        $fallbackPrices = Price::whereIn('type_id', $typeIds)->get()->keyBy('type_id');
        $typeVolumes = $this->packagedVolumes($typeIds);

        $reports = [];
        $totals = [
            'desired_value' => 0,
            'seeded_value' => 0,
            'restock_cost' => 0,
            'restock_volume' => 0,
            'missing_lines' => 0,
            'desired_quantity' => 0,
            'covered_quantity' => 0,
            'health_score' => 100,
        ];

        foreach ($markets as $market) {
            $marketTotals = [
                'desired_value' => 0,
                'seeded_value' => 0,
                'restock_cost' => 0,
                'restock_volume' => 0,
                'missing_lines' => 0,
                'desired_quantity' => 0,
                'covered_quantity' => 0,
                'health_score' => 100,
            ];

            $rows = $market->items->sortBy('type_name')->map(function ($item) use ($market, $localOrders, $jitaPrices, $fallbackPrices, $typeVolumes, &$marketTotals) {
                $key = $market->location_id . ':' . $item->type_id;
                $local = $localOrders->get($key);
                $currentQuantity = $local ? (int) $local->quantity : 0;
                $localPrice = $local ? (float) $local->price : null;
                $jitaPrice = $jitaPrices->get($item->type_id);
                $itemVolume = (float) $typeVolumes->get($item->type_id, 0);

                if (!$jitaPrice && $fallbackPrices->has($item->type_id)) {
                    $jitaPrice = (float) ($fallbackPrices->get($item->type_id)->sell_price ?: $fallbackPrices->get($item->type_id)->average_price);
                }

                $missingQuantity = max(0, $item->desired_quantity - $currentQuantity);
                $warningQuantity = (int) $item->warning_quantity;
                $isLow = $currentQuantity < $warningQuantity;
                $priceDelta = $localPrice && $jitaPrice ? (($localPrice - $jitaPrice) / $jitaPrice) * 100 : null;
                $restockCost = $missingQuantity * (float) $jitaPrice;
                $restockVolume = $missingQuantity * $itemVolume;
                $desiredValue = $item->desired_quantity * (float) $jitaPrice;
                $seededValue = $currentQuantity * (float) ($localPrice ?: $jitaPrice);
                $coveredQuantity = min($currentQuantity, (int) $item->desired_quantity);

                $marketTotals['desired_value'] += $desiredValue;
                $marketTotals['seeded_value'] += $seededValue;
                $marketTotals['restock_cost'] += $restockCost;
                $marketTotals['restock_volume'] += $restockVolume;
                $marketTotals['missing_lines'] += $missingQuantity > 0 ? 1 : 0;
                $marketTotals['desired_quantity'] += (int) $item->desired_quantity;
                $marketTotals['covered_quantity'] += $coveredQuantity;

                return [
                    'item' => $item,
                    'type_category' => $item->typeCategoryName(),
                    'source_flags' => $item->sourceFlags(),
                    'current_quantity' => $currentQuantity,
                    'missing_quantity' => $missingQuantity,
                    'local_price' => $localPrice,
                    'jita_price' => $jitaPrice,
                    'price_delta' => $priceDelta,
                    'restock_cost' => $restockCost,
                    'item_volume' => $itemVolume,
                    'restock_volume' => $restockVolume,
                    'seeded_value' => $seededValue,
                    'desired_value' => $desiredValue,
                    'is_low' => $isLow,
                    'export_line' => $missingQuantity > 0 ? $item->type_name . "\t" . $missingQuantity : null,
                ];
            })->values();

            $marketTotals['health_score'] = $this->healthScore($marketTotals['covered_quantity'], $marketTotals['desired_quantity']);

            foreach ($marketTotals as $key => $value) {
                $totals[$key] += $value;
            }

            $reports[] = [
                'market' => $market,
                'rows' => $rows,
                'totals' => $marketTotals,
                'export' => $rows->pluck('export_line')->filter()->implode("\n"),
            ];
        }

        $totals['health_score'] = $this->healthScore($totals['covered_quantity'], $totals['desired_quantity']);

        return [
            'markets' => $reports,
            'totals' => $totals,
        ];
    }

    public function itemDetails(SeededMarketItem $item): array
    {
        return $this->itemDetailsForItems(collect([$item]))->get($item->id, []);
    }

    public function itemDetailsForItems(Collection $items): Collection
    {
        if ($items->isEmpty()) {
            return collect();
        }

        if ($items instanceof EloquentCollection) {
            $items->loadMissing('market', 'sources', 'type.group');
        } else {
            $items->each(function (SeededMarketItem $item) {
                $item->loadMissing('market', 'sources', 'type.group');
            });
        }

        $typeIds = $items->pluck('type_id')->map(fn ($typeId) => (int) $typeId)->unique()->values();
        $locationIds = $items
            ->pluck('market.location_id')
            ->filter()
            ->map(fn ($locationId) => (int) $locationId)
            ->unique()
            ->values();
        $localOrders = $this->localSellOrders($locationIds, $typeIds);
        $jitaPrices = $this->jitaSellPrices($typeIds);
        $fallbackPrices = Price::whereIn('type_id', $typeIds)->get()->keyBy('type_id');
        $typeVolumes = $this->packagedVolumes($typeIds);

        return $items->mapWithKeys(function (SeededMarketItem $item) use ($localOrders, $jitaPrices, $fallbackPrices, $typeVolumes) {
            $market = $item->market;
            $local = $market
                ? $localOrders->get($market->location_id . ':' . $item->type_id)
                : null;
            $jitaPrice = $jitaPrices->get($item->type_id);
            $fallbackPrice = $fallbackPrices->get($item->type_id);
            $itemVolume = (float) $typeVolumes->get($item->type_id, 0);

            if (!$jitaPrice && $fallbackPrice) {
                $jitaPrice = (float) ($fallbackPrice->sell_price ?: $fallbackPrice->average_price);
            }

            $currentQuantity = $local ? (int) $local->quantity : 0;
            $localPrice = $local ? (float) $local->price : null;
            $missingQuantity = max(0, (int) $item->desired_quantity - $currentQuantity);
            $priceDelta = $localPrice && $jitaPrice ? (($localPrice - $jitaPrice) / $jitaPrice) * 100 : null;
            $restockCost = $missingQuantity * (float) $jitaPrice;
            $restockVolume = $missingQuantity * $itemVolume;
            $seededValue = $currentQuantity * (float) ($localPrice ?: $jitaPrice);
            $desiredValue = (int) $item->desired_quantity * (float) $jitaPrice;

            return [$item->id => [
                'type_category' => $item->typeCategoryName(),
                'source_flags' => $item->sourceFlags(),
                'current_quantity' => $currentQuantity,
                'desired_quantity' => (int) $item->desired_quantity,
                'warning_quantity' => (int) $item->warning_quantity,
                'missing_quantity' => $missingQuantity,
                'local_price' => $localPrice,
                'jita_price' => $jitaPrice,
                'price_delta' => $priceDelta,
                'seeded_value' => $seededValue,
                'desired_value' => $desiredValue,
                'restock_cost' => $restockCost,
                'item_volume' => $itemVolume,
                'restock_volume' => $restockVolume,
                'stock_status' => $item->stock_status,
            ]];
        });
    }

    private function loadMarketRelations(Collection $markets): void
    {
        if ($markets instanceof EloquentCollection) {
            $markets->loadMissing('items.sources', 'items.type.group', 'role');

            return;
        }

        $markets->each(function (SeededMarket $market) {
            $market->loadMissing('items.sources', 'items.type.group', 'role');
        });
    }

    private function healthScore(int $coveredQuantity, int $desiredQuantity): float
    {
        if ($desiredQuantity <= 0) {
            return 100.0;
        }

        return round(min(100, ($coveredQuantity / $desiredQuantity) * 100), 1);
    }

    private function packagedVolumes(Collection $typeIds): Collection
    {
        if ($typeIds->isEmpty()) {
            return collect();
        }

        $typeIds = $typeIds->map(fn ($typeId) => (int) $typeId)->sort()->values();
        $cacheKey = 'seat-market-seeding:packaged-volumes:' . md5($typeIds->implode(','));

        return Cache::remember($cacheKey, now()->addDay(), function () use ($typeIds) {
            return InvType::with('group')
                ->whereIn('typeID', $typeIds)
                ->get()
                ->mapWithKeys(function (InvType $type) {
                    return [$type->typeID => $this->packagedVolume($type)];
                });
        });
    }

    private function packagedVolume(InvType $type): float
    {
        if (array_key_exists($type->typeName, self::TYPE_PACKAGED_VOLUME_OVERRIDES)) {
            return (float) self::TYPE_PACKAGED_VOLUME_OVERRIDES[$type->typeName];
        }

        if ((int) optional($type->group)->categoryID !== self::SHIP_CATEGORY_ID) {
            return (float) $type->volume;
        }

        return (float) (self::SHIP_PACKAGED_VOLUMES[$type->group->groupName] ?? $type->volume);
    }

    private function localSellOrders(Collection $locationIds, Collection $typeIds): Collection
    {
        if ($locationIds->isEmpty() || $typeIds->isEmpty()) {
            return collect();
        }

        return MarketOrder::query()
            ->selectRaw('location_id, type_id, SUM(volume_remaining) as quantity, MIN(price) as price')
            ->whereIn('location_id', $locationIds)
            ->whereIn('type_id', $typeIds)
            ->where('is_buy_order', false)
            ->groupBy('location_id', 'type_id')
            ->get()
            ->keyBy(function ($row) {
                return $row->location_id . ':' . $row->type_id;
            });
    }

    private function jitaSellPrices(Collection $typeIds): Collection
    {
        if ($typeIds->isEmpty()) {
            return collect();
        }

        return MarketOrder::query()
            ->selectRaw('type_id, MIN(price) as price')
            ->where('location_id', self::JITA_STATION_ID)
            ->whereIn('type_id', $typeIds)
            ->where('is_buy_order', false)
            ->groupBy('type_id')
            ->pluck('price', 'type_id')
            ->map(function ($price) {
                return (float) $price;
            });
    }
}
