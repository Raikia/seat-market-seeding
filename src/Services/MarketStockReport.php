<?php

namespace Raikia\SeatMarketSeeding\Services;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Raikia\SeatMarketSeeding\Models\SeededMarket;
use Seat\Eveapi\Models\Market\MarketOrder;
use Seat\Eveapi\Models\Market\Price;

class MarketStockReport
{
    const JITA_STATION_ID = 60003760;

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

        $reports = [];
        $totals = [
            'desired_value' => 0,
            'seeded_value' => 0,
            'restock_cost' => 0,
            'missing_lines' => 0,
        ];

        foreach ($markets as $market) {
            $marketTotals = [
                'desired_value' => 0,
                'seeded_value' => 0,
                'restock_cost' => 0,
                'missing_lines' => 0,
            ];

            $rows = $market->items->sortBy('type_name')->map(function ($item) use ($market, $localOrders, $jitaPrices, $fallbackPrices, &$marketTotals) {
                $key = $market->location_id . ':' . $item->type_id;
                $local = $localOrders->get($key);
                $currentQuantity = $local ? (int) $local->quantity : 0;
                $localPrice = $local ? (float) $local->price : null;
                $jitaPrice = $jitaPrices->get($item->type_id);

                if (!$jitaPrice && $fallbackPrices->has($item->type_id)) {
                    $jitaPrice = (float) ($fallbackPrices->get($item->type_id)->sell_price ?: $fallbackPrices->get($item->type_id)->average_price);
                }

                $missingQuantity = max(0, $item->desired_quantity - $currentQuantity);
                $warningQuantity = $item->warning_quantity ?: $item->desired_quantity;
                $isLow = $currentQuantity < $warningQuantity;
                $priceDelta = $localPrice && $jitaPrice ? (($localPrice - $jitaPrice) / $jitaPrice) * 100 : null;
                $restockCost = $missingQuantity * (float) $jitaPrice;
                $desiredValue = $item->desired_quantity * (float) $jitaPrice;
                $seededValue = $currentQuantity * (float) ($localPrice ?: $jitaPrice);

                $marketTotals['desired_value'] += $desiredValue;
                $marketTotals['seeded_value'] += $seededValue;
                $marketTotals['restock_cost'] += $restockCost;
                $marketTotals['missing_lines'] += $missingQuantity > 0 ? 1 : 0;

                return [
                    'item' => $item,
                    'current_quantity' => $currentQuantity,
                    'missing_quantity' => $missingQuantity,
                    'local_price' => $localPrice,
                    'jita_price' => $jitaPrice,
                    'price_delta' => $priceDelta,
                    'restock_cost' => $restockCost,
                    'seeded_value' => $seededValue,
                    'desired_value' => $desiredValue,
                    'is_low' => $isLow,
                    'export_line' => $missingQuantity > 0 ? $item->type_name . "\t" . $missingQuantity : null,
                ];
            })->values();

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

        return [
            'markets' => $reports,
            'totals' => $totals,
        ];
    }

    private function loadMarketRelations(Collection $markets): void
    {
        if ($markets instanceof EloquentCollection) {
            $markets->load('items', 'role');

            return;
        }

        $markets->each(function (SeededMarket $market) {
            $market->loadMissing('items', 'role');
        });
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
