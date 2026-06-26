<?php

namespace Raikia\SeatMarketSeeding\Services;

use Illuminate\Support\Collection;
use Raikia\SeatMarketSeeding\Models\MarketStockHistory;
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

        $market->loadMissing('items');

        if ($market->items->isEmpty()) {
            return 0;
        }

        $quantities = $this->currentQuantities($market);
        $notifications = 0;
        $restockedItems = collect();

        foreach ($market->items as $item) {
            $currentQuantity = (int) $quantities->get($item->type_id, 0);
            $currentStatus = $this->statusFor($item, $currentQuantity);
            $previousStatus = $item->stock_status;

            if ($previousStatus && $previousStatus !== $currentStatus) {
                $this->recordHistory($market, $item, $previousStatus, $currentStatus, $currentQuantity);

                if ($this->isRestockedTransition($previousStatus, $currentStatus)) {
                    $restockedItems->push($this->restockedItemPayload($item, $previousStatus, $currentStatus, $currentQuantity));
                } else {
                    $notifications += $this->dispatchTransition($market, $item, $previousStatus, $currentStatus, $currentQuantity);
                }
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
        MarketStockHistory::query()
            ->where('created_at', '<', now()->subDays($this->settings->historyRetentionDays()))
            ->delete();
    }
}
