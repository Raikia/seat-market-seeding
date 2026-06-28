<?php

namespace Raikia\SeatMarketSeeding\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Raikia\SeatMarketSeeding\Models\MarketStockDailySummary;
use Raikia\SeatMarketSeeding\Models\MarketStockHistory;
use Raikia\SeatMarketSeeding\Models\MarketStockSnapshot;
use Raikia\SeatMarketSeeding\Models\SeededMarket;
use Raikia\SeatMarketSeeding\Models\SeededMarketItem;
use Seat\Eveapi\Models\Market\MarketOrder;
use Seat\Notifications\Models\NotificationGroup;
use Seat\Notifications\Traits\NotificationDispatchTool;

class MarketStockTransitionNotifier
{
    use NotificationDispatchTool;

    const STATUS_STOCKED = 'stocked';
    const STATUS_LOW = 'low';
    const STATUS_EMPTY = 'empty';

    const ALERT_LOW_STOCK = 'market_seeding_low_stock';
    const ALERT_EMPTY_STOCK = 'market_seeding_empty_stock';
    const ALERT_RESTOCKED = 'market_seeding_restocked';

    private MarketSeedingSettings $settings;
    private bool $historyPruned = false;

    public function __construct(MarketSeedingSettings $settings)
    {
        $this->settings = $settings;
    }

    public function checkMarket(SeededMarket $market): int
    {
        $this->pruneHistory();

        if (!class_exists(NotificationGroup::class)) {
            return 0;
        }

        $market->loadMissing('items.type.group');

        if ($market->items->isEmpty()) {
            return 0;
        }

        $quantities = $this->currentQuantities($market);
        $previousSnapshots = $this->previousSnapshots($market);
        $notifications = 0;
        $restockedItems = collect();

        foreach ($market->items as $item) {
            $currentQuantity = (int) $quantities->get($item->type_id, 0);
            $currentStatus = $this->statusFor($item, $currentQuantity);
            $previousStatus = $item->stock_status;
            $snapshot = $this->recordSnapshot($market, $item, $previousSnapshots->get($item->id), $currentQuantity);

            if ($previousStatus && $previousStatus !== $currentStatus) {
                $this->recordHistory($market, $item, $previousStatus, $currentStatus, $currentQuantity);
                $this->recordDailySummary($snapshot, $currentStatus);

                if ($this->isRestockedTransition($previousStatus, $currentStatus)) {
                    $restockedItems->push($this->restockedItemPayload($item, $previousStatus, $currentStatus, $currentQuantity));
                } else {
                    $notifications += $this->dispatchTransition($market, $item, $previousStatus, $currentStatus, $currentQuantity);
                }
            } else {
                $this->recordDailySummary($snapshot);
            }


            if ($previousStatus !== $currentStatus) {
                $item->stock_status = $currentStatus;
                $item->save();
            }
        }

        $notifications += $this->dispatchRestocked($market, $restockedItems);

        return $notifications;
    }

    private function currentQuantities(SeededMarket $market): Collection
    {
        return MarketOrder::query()
            ->where('location_id', $market->location_id)
            ->whereIn('type_id', $market->items->pluck('type_id'))
            ->where('is_buy_order', false)
            ->groupBy('type_id')
            ->selectRaw('type_id, SUM(volume_remaining) as quantity')
            ->pluck('quantity', 'type_id')
            ->map(function ($quantity) {
                return (int) $quantity;
            });
    }

    private function previousSnapshots(SeededMarket $market): Collection
    {
        $itemIds = $market->items->pluck('id');

        if ($itemIds->isEmpty()) {
            return collect();
        }

        return MarketStockSnapshot::query()
            ->whereIn('id', function ($query) use ($market, $itemIds) {
                $query->from((new MarketStockSnapshot)->getTable())
                    ->select(DB::raw('MAX(id)'))
                    ->where('market_id', $market->id)
                    ->whereIn('item_id', $itemIds)
                    ->groupBy('item_id');
            })
            ->get()
            ->keyBy('item_id');
    }

    private function statusFor(SeededMarketItem $item, int $currentQuantity): string
    {
        if ($currentQuantity <= 0) {
            return self::STATUS_EMPTY;
        }

        $warningQuantity = (int) $item->warning_quantity;

        if ($currentQuantity < $warningQuantity) {
            return self::STATUS_LOW;
        }

        return self::STATUS_STOCKED;
    }

    private function dispatchTransition(SeededMarket $market, SeededMarketItem $item, string $previousStatus, string $currentStatus, int $currentQuantity): int
    {
        if ($previousStatus === self::STATUS_STOCKED && $currentStatus === self::STATUS_LOW) {
            return $this->dispatchAlert(self::ALERT_LOW_STOCK, $market, $item, $previousStatus, $currentStatus, $currentQuantity);
        }

        if ($currentStatus === self::STATUS_EMPTY && in_array($previousStatus, [self::STATUS_STOCKED, self::STATUS_LOW], true)) {
            return $this->dispatchAlert(self::ALERT_EMPTY_STOCK, $market, $item, $previousStatus, $currentStatus, $currentQuantity);
        }

        return 0;
    }

    private function isRestockedTransition(string $previousStatus, string $currentStatus): bool
    {
        return $currentStatus === self::STATUS_STOCKED
            && in_array($previousStatus, [self::STATUS_LOW, self::STATUS_EMPTY], true);
    }

    private function dispatchRestocked(SeededMarket $market, Collection $items): int
    {
        if ($items->isEmpty()) {
            return 0;
        }

        $groups = NotificationGroup::with('alerts', 'integrations', 'mentions')
            ->whereHas('alerts', function ($query) {
                $query->where('alert', self::ALERT_RESTOCKED);
            })
            ->get();

        if ($groups->isEmpty()) {
            return 0;
        }

        $payload = [
            'alert_type' => self::ALERT_RESTOCKED,
            'market_id' => $market->id,
            'market_name' => $market->name,
            'location_name' => $market->location_name,
            'items' => $items->values()->all(),
            'item_count' => $items->count(),
            'dashboard_url' => route('market-seeding.index'),
            'timestamp' => now()->toIso8601String(),
        ];

        $this->dispatchNotifications(self::ALERT_RESTOCKED, $groups, function ($notificationClass) use ($payload) {
            return new $notificationClass($payload);
        });

        return 1;
    }

    private function dispatchAlert(string $alertType, SeededMarket $market, SeededMarketItem $item, string $previousStatus, string $currentStatus, int $currentQuantity): int
    {
        $groups = NotificationGroup::with('alerts', 'integrations', 'mentions')
            ->whereHas('alerts', function ($query) use ($alertType) {
                $query->where('alert', $alertType);
            })
            ->get();

        if ($groups->isEmpty()) {
            return 0;
        }

        $payload = [
            'alert_type' => $alertType,
            'market_id' => $market->id,
            'market_name' => $market->name,
            'location_name' => $market->location_name,
            'type_id' => $item->type_id,
            'type_name' => $item->type_name,
            'desired_quantity' => $item->desired_quantity,
            'warning_quantity' => (int) $item->warning_quantity,
            'current_quantity' => $currentQuantity,
            'previous_status' => $previousStatus,
            'current_status' => $currentStatus,
            'dashboard_url' => route('market-seeding.index'),
            'timestamp' => now()->toIso8601String(),
        ];

        $this->dispatchNotifications($alertType, $groups, function ($notificationClass) use ($payload) {
            return new $notificationClass($payload);
        });

        return 1;
    }

    private function restockedItemPayload(SeededMarketItem $item, string $previousStatus, string $currentStatus, int $currentQuantity): array
    {
        return [
            'type_id' => $item->type_id,
            'type_name' => $item->type_name,
            'desired_quantity' => $item->desired_quantity,
            'warning_quantity' => (int) $item->warning_quantity,
            'current_quantity' => $currentQuantity,
            'previous_status' => $previousStatus,
            'current_status' => $currentStatus,
        ];
    }

    private function recordSnapshot(SeededMarket $market, SeededMarketItem $item, ?MarketStockSnapshot $previousSnapshot, int $currentQuantity): MarketStockSnapshot
    {
        $previousQuantity = optional($previousSnapshot)->current_quantity;
        $estimatedSoldQuantity = $previousQuantity === null ? 0 : max(0, (int) $previousQuantity - $currentQuantity);
        $restockedQuantity = $previousQuantity === null ? 0 : max(0, $currentQuantity - (int) $previousQuantity);

        return MarketStockSnapshot::create([
            'market_id' => $market->id,
            'item_id' => $item->id,
            'role_id' => $market->role_id,
            'type_id' => $item->type_id,
            'market_name' => $market->name,
            'location_name' => $market->location_name,
            'type_name' => $item->type_name,
            'type_category' => $item->typeCategoryName(),
            'previous_quantity' => $previousQuantity,
            'current_quantity' => $currentQuantity,
            'estimated_sold_quantity' => $estimatedSoldQuantity,
            'restocked_quantity' => $restockedQuantity,
            'warning_quantity' => (int) $item->warning_quantity,
            'desired_quantity' => $item->desired_quantity,
        ]);
    }

    private function recordDailySummary(MarketStockSnapshot $snapshot, ?string $transitionStatus = null): void
    {
        if (!Schema::hasTable('seat_market_seeding_stock_daily_summaries')) {
            return;
        }

        $summary = MarketStockDailySummary::firstOrNew([
            'summary_date' => $snapshot->created_at->toDateString(),
            'market_id' => $snapshot->market_id,
            'item_id' => $snapshot->item_id,
            'type_id' => $snapshot->type_id,
        ]);

        if (!$summary->exists) {
            $summary->estimated_sold_quantity = 0;
            $summary->restocked_quantity = 0;
            $summary->sales_events = 0;
            $summary->low_events = 0;
            $summary->empty_events = 0;
            $summary->stocked_events = 0;
            $summary->total_shortage = 0;
        }

        $summary->role_id = $snapshot->role_id;
        $summary->market_name = $snapshot->market_name;
        $summary->location_name = $snapshot->location_name;
        $summary->type_name = $snapshot->type_name;
        $summary->type_category = $snapshot->type_category;
        $summary->estimated_sold_quantity = (int) $summary->estimated_sold_quantity + (int) $snapshot->estimated_sold_quantity;
        $summary->restocked_quantity = (int) $summary->restocked_quantity + (int) $snapshot->restocked_quantity;
        $summary->sales_events = (int) $summary->sales_events + ((int) $snapshot->estimated_sold_quantity > 0 ? 1 : 0);
        $summary->latest_current_quantity = (int) $snapshot->current_quantity;
        $summary->latest_desired_quantity = (int) $snapshot->desired_quantity;
        $summary->latest_warning_quantity = (int) $snapshot->warning_quantity;

        if ((int) $snapshot->estimated_sold_quantity > 0) {
            $summary->last_sold_at = $snapshot->created_at;
        }

        if ($transitionStatus === self::STATUS_LOW) {
            $summary->low_events = (int) $summary->low_events + 1;
        } elseif ($transitionStatus === self::STATUS_EMPTY) {
            $summary->empty_events = (int) $summary->empty_events + 1;
        } elseif ($transitionStatus === self::STATUS_STOCKED) {
            $summary->stocked_events = (int) $summary->stocked_events + 1;
        }

        if (in_array($transitionStatus, [self::STATUS_LOW, self::STATUS_EMPTY], true)) {
            $summary->total_shortage = (int) $summary->total_shortage + max(0, (int) $snapshot->desired_quantity - (int) $snapshot->current_quantity);
            $summary->last_needed_at = $snapshot->created_at;
        }

        $summary->save();
    }

    private function recordHistory(SeededMarket $market, SeededMarketItem $item, string $previousStatus, string $currentStatus, int $currentQuantity): void
    {
        MarketStockHistory::create([
            'market_id' => $market->id,
            'item_id' => $item->id,
            'role_id' => $market->role_id,
            'type_id' => $item->type_id,
            'market_name' => $market->name,
            'location_name' => $market->location_name,
            'type_name' => $item->type_name,
            'previous_status' => $previousStatus,
            'current_status' => $currentStatus,
            'current_quantity' => $currentQuantity,
            'warning_quantity' => (int) $item->warning_quantity,
            'desired_quantity' => $item->desired_quantity,
        ]);
    }

    private function pruneHistory(): void
    {
        if ($this->historyPruned) {
            return;
        }

        $this->historyPruned = true;

        MarketStockHistory::query()
            ->where('created_at', '<', now()->subDays($this->settings->historyRetentionDays()))
            ->delete();

        MarketStockSnapshot::query()
            ->where('created_at', '<', now()->subDays($this->settings->historyRetentionDays()))
            ->delete();

        if (Schema::hasTable('seat_market_seeding_stock_daily_summaries')) {
            MarketStockDailySummary::query()
                ->where('summary_date', '<', now()->subDays($this->settings->historyRetentionDays())->toDateString())
                ->delete();
        }
    }
}
