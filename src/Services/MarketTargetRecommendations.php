<?php

namespace Raikia\SeatMarketSeeding\Services;

use Illuminate\Support\Collection;
use Raikia\SeatMarketSeeding\Models\MarketStockDailySummary;
use Raikia\SeatMarketSeeding\Models\SeededMarket;
use Raikia\SeatMarketSeeding\Models\SeededMarketItem;

class MarketTargetRecommendations
{
    private const MIN_DAYS_WITH_DATA = 7;

    public function forMarkets($markets, int $salesDays, int $bufferPercentage, MarketStockReport $report, bool $onlyDiffering = true): Collection
    {
        $markets = collect($markets);
        $marketIds = $markets->pluck('id')->filter()->values();

        if ($marketIds->isEmpty()) {
            return collect();
        }

        $rows = MarketStockDailySummary::query()
            ->whereIn('market_id', $marketIds)
            ->whereNotNull('item_id')
            ->where('summary_date', '>=', now()->subDays($salesDays - 1)->toDateString())
            ->select([
                'market_id',
                'item_id',
                'market_name',
                'location_name',
                'type_id',
                'type_name',
                'type_category',
            ])
            ->selectRaw('SUM(estimated_sold_quantity) as estimated_sold')
            ->selectRaw('SUM(restocked_quantity) as restocked')
            ->selectRaw('SUM(sales_events) as sales_events')
            ->selectRaw('SUM(low_events + empty_events) as low_empty_events')
            ->selectRaw('MIN(summary_date) as oldest_summary_date')
            ->groupBy('market_id', 'item_id', 'market_name', 'location_name', 'type_id', 'type_name', 'type_category')
            ->get();

        $this->attachCurrentTargets($rows);
        $this->attachRecommendations($rows, $salesDays, $bufferPercentage);
        $this->attachEconomics($rows, $report);

        $rows = $onlyDiffering
            ? $rows->filter(fn ($row) => $row->recommendation_differs)
            : $rows;

        return $rows
            ->sortByDesc(fn ($row) => (int) $row->recommended_quantity - (int) $row->current_target_quantity)
            ->groupBy('market_id')
            ->map(fn ($rows) => $rows->values())
            ->mapWithKeys(fn ($rows, $marketId) => [(int) $marketId => $rows])
            ->union($markets->mapWithKeys(fn (SeededMarket $market) => [$market->id => collect()]));
    }

    public function recommendedWarningQuantity($recommendation): int
    {
        $currentTarget = max(1, (int) $recommendation->current_target_quantity);
        $currentWarning = max(0, (int) $recommendation->warning_quantity);

        if ($currentWarning === 0) {
            return 0;
        }

        return app(StockTargetQuantity::class)->scaleWarningQuantity(
            max(1, (int) $recommendation->recommended_quantity),
            $currentTarget,
            $currentWarning
        );
    }

    public function payload($row): array
    {
        return [
            'item_id' => (int) $row->item_id,
            'market_id' => (int) $row->market_id,
            'type_name' => $row->type_name,
            'type_category' => $row->type_category,
            'market_name' => $row->market_name,
            'location_name' => $row->location_name,
            'current_target_quantity' => (int) $row->current_target_quantity,
            'warning_quantity' => (int) $row->warning_quantity,
            'recommended_quantity' => (int) $row->recommended_quantity,
            'recommended_warning_quantity' => $this->recommendedWarningQuantity($row),
            'recommendation_reason' => $row->recommendation_reason,
            'recommendation_estimated_sold' => (int) $row->recommendation_estimated_sold,
            'low_empty_events' => (int) ($row->low_empty_events ?? 0),
            'recommendation_sales_days_with_data' => (int) $row->recommendation_sales_days_with_data,
            'recommendation_daily_sold' => (float) $row->recommendation_daily_sold,
            'recommendation_sales_window' => (int) $row->recommendation_sales_window,
            'recommendation_buffer_multiplier' => (float) $row->recommendation_buffer_multiplier,
            'recommendation_sales_target' => (int) $row->recommendation_sales_target,
            'recommendation_existing_target_covers' => (bool) $row->recommendation_existing_target_covers,
            'recommendation_delta_quantity' => (int) $row->recommendation_delta_quantity,
            'recommendation_delta_cost' => (float) $row->recommendation_delta_cost,
            'recommendation_delta_volume' => (float) $row->recommendation_delta_volume,
            'recommendation_unit_cost' => (float) $row->recommendation_unit_cost,
            'recommendation_unit_volume' => (float) $row->recommendation_unit_volume,
            'source_flags' => $row->source_flags ?? ['manual' => false, 'doctrine' => false, 'fitting' => false],
        ];
    }

    private function attachCurrentTargets(Collection $rows): void
    {
        $items = SeededMarketItem::query()
            ->with('sources')
            ->whereIn('id', $rows->pluck('item_id')->filter()->unique()->values())
            ->get()
            ->keyBy('id');

        foreach ($rows as $row) {
            $item = $items->get($row->item_id);

            if (!$item) {
                $row->target_quantity = 1;
                $row->desired_quantity = 1;
                $row->warning_quantity = 0;
                $row->source_flags = ['manual' => false, 'doctrine' => false, 'fitting' => false];
                continue;
            }

            $row->target_quantity = (int) $item->desired_quantity;
            $row->desired_quantity = (int) $item->desired_quantity;
            $row->warning_quantity = (int) $item->warning_quantity;
            $row->source_flags = $item->sourceFlags();
        }
    }

    private function attachRecommendations(Collection $rows, int $salesDays, int $bufferPercentage): void
    {
        foreach ($rows as $row) {
            $target = max(1, (int) ($row->target_quantity ?? $row->desired_quantity ?? 1));
            $estimatedSold = (int) ($row->estimated_sold ?? 0);
            $days = $this->coverageDaysFromSummaryDate($row->oldest_summary_date, $salesDays);
            $dailySold = $days > 0 ? $estimatedSold / $days : 0;
            $bufferMultiplier = 1 + ($bufferPercentage / 100);
            $salesRecommendation = (int) ceil($dailySold * $salesDays * $bufferMultiplier);
            $recommended = max(1, $target, $salesRecommendation);

            $row->current_target_quantity = $target;
            $row->recommended_quantity = $recommended;
            $row->recommendation_differs = $recommended !== $target;
            $row->recommendation_sales_days_with_data = $days;
            $row->recommendation_estimated_sold = $estimatedSold;
            $row->recommendation_daily_sold = round($dailySold, 2);
            $row->recommendation_sales_window = $salesDays;
            $row->recommendation_buffer_multiplier = $bufferMultiplier;
            $row->recommendation_sales_target = max(1, $salesRecommendation);
            $row->recommendation_existing_target_covers = $target >= max(1, $salesRecommendation);

            if ($days < self::MIN_DAYS_WITH_DATA) {
                $row->recommended_quantity = $target;
                $row->recommendation_differs = false;
                $row->recommendation_reason = sprintf(
                    "No recommendation is shown yet because this item only has %s day%s of sales history.\n\nAt least %s days with data are required before target recommendations are made.\n\nCurrent sales signal: %s estimated sold, which is about %s per day.",
                    number_format($days),
                    $days === 1 ? '' : 's',
                    number_format(self::MIN_DAYS_WITH_DATA),
                    number_format($estimatedSold),
                    number_format($dailySold, 2)
                );
                continue;
            }

            if ($recommended <= $target) {
                $row->recommendation_reason = sprintf(
                    "Current target: %s\n\nSales signal: In the last %s days with data, this item had %s estimated sold, which is about %s per day.\n\nFormula: %s sold / %s days * %s sales days * %s buffer = %s.\n\nResult: The sales-based target does not exceed the current target, so no increase is recommended.\n\nNote: Low or empty stock events are shown elsewhere for context, but they are not used to calculate target recommendations.",
                    number_format($target),
                    number_format($days),
                    number_format($estimatedSold),
                    number_format($dailySold, 2),
                    number_format($estimatedSold),
                    number_format($days),
                    number_format($salesDays),
                    number_format($bufferMultiplier, 2) . 'x',
                    number_format($salesRecommendation)
                );
                continue;
            }

            $row->recommendation_reason = sprintf(
                "Recommended because recent estimated sales suggest the current target may not cover enough days of demand.\n\nCurrent target: %s\n\nSales signal: In the last %s days with data, this item had %s estimated sold, which is about %s per day.\n\nFormula: %s sold / %s days * %s sales days * %s buffer = %s.\n\nResult: Recent sales pace is driving this recommendation.\n\nNote: Low or empty stock events are shown elsewhere for context, but they are not used to calculate target recommendations.",
                number_format($target),
                number_format($days),
                number_format($estimatedSold),
                number_format($dailySold, 2),
                number_format($estimatedSold),
                number_format($days),
                number_format($salesDays),
                number_format($bufferMultiplier, 2) . 'x',
                number_format($salesRecommendation)
            );
        }
    }

    private function attachEconomics(Collection $rows, MarketStockReport $report): void
    {
        $items = SeededMarketItem::query()
            ->with('market')
            ->whereIn('id', $rows->pluck('item_id')->filter()->unique()->values())
            ->get()
            ->keyBy('id');
        $detailsByItem = $report->itemDetailsForItems($items->values());

        foreach ($rows as $row) {
            $details = $detailsByItem->get($row->item_id, []);
            $deltaQuantity = max(0, (int) $row->recommended_quantity - (int) $row->current_target_quantity);
            $jitaPrice = (float) ($details['jita_price'] ?? 0);
            $itemVolume = (float) ($details['item_volume'] ?? 0);

            $row->recommendation_unit_cost = $jitaPrice;
            $row->recommendation_unit_volume = $itemVolume;
            $row->recommendation_delta_quantity = $deltaQuantity;
            $row->recommendation_delta_cost = $deltaQuantity * $jitaPrice;
            $row->recommendation_delta_volume = $deltaQuantity * $itemVolume;
        }
    }

    private function coverageDaysFromSummaryDate($oldestSummaryDate, int $days): int
    {
        if (!$oldestSummaryDate) {
            return max(1, $days);
        }

        $coveredDays = \Carbon\Carbon::parse($oldestSummaryDate)
            ->startOfDay()
            ->diffInDays(now()->startOfDay()) + 1;

        return max(1, min($days, (int) $coveredDays));
    }
}
