<?php

namespace Raikia\SeatMarketSeeding\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Raikia\SeatMarketSeeding\Models\MarketSeedingNotificationGroupFilter;
use Raikia\SeatMarketSeeding\Models\SeededMarket;
use Raikia\SeatMarketSeeding\Services\MarketStockTransitionNotifier;
use Seat\Notifications\Models\NotificationGroup;
use Seat\Web\Http\Controllers\Controller;

class NotificationSettingsController extends Controller
{
    const MARKET_SEEDING_ALERTS = [
        MarketStockTransitionNotifier::ALERT_LOW_STOCK,
        MarketStockTransitionNotifier::ALERT_EMPTY_STOCK,
        MarketStockTransitionNotifier::ALERT_RESTOCKED,
    ];

    public function index()
    {
        $markets = SeededMarket::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $notificationGroups = collect();
        $notificationGroupFilters = collect();

        if (class_exists(NotificationGroup::class)) {
            $notificationGroups = NotificationGroup::with(['alerts', 'integrations'])
                ->whereHas('alerts', function ($query) {
                    $query->whereIn('alert', self::MARKET_SEEDING_ALERTS);
                })
                ->orderBy('name')
                ->get();

            if (Schema::hasTable('seat_market_seeding_notification_group_filters')) {
                $notificationGroupFilters = MarketSeedingNotificationGroupFilter::query()
                    ->whereIn('notification_group_id', $notificationGroups->pluck('id'))
                    ->get()
                    ->keyBy('notification_group_id');
            }
        }

        return view('seat-market-seeding::notifications', compact('markets', 'notificationGroups', 'notificationGroupFilters'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'notification_group_filters' => 'nullable|array',
            'notification_group_filters.*.notification_group_id' => 'required|integer|exists:notification_groups,id',
            'notification_group_filters.*.allowed_market_ids' => 'nullable|array',
            'notification_group_filters.*.allowed_market_ids.*' => 'integer|exists:seat_market_seeding_markets,id',
        ]);

        $groupFilters = collect($request->input('notification_group_filters', []))
            ->filter(fn ($filter) => ! empty($filter['notification_group_id']))
            ->keyBy(fn ($filter) => (int) $filter['notification_group_id']);
        $groupIds = $groupFilters->pluck('notification_group_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        DB::transaction(function () use ($groupFilters, $groupIds) {
            if (! empty($groupIds)) {
                MarketSeedingNotificationGroupFilter::whereIn('notification_group_id', $groupIds)->delete();
            }

            $groupFilters->each(function (array $filter) {
                $allowedMarketIds = $this->normalizeMarketIds($filter['allowed_market_ids'] ?? []);

                if (empty($allowedMarketIds)) {
                    return;
                }

                MarketSeedingNotificationGroupFilter::create([
                    'notification_group_id' => (int) $filter['notification_group_id'],
                    'allowed_market_ids' => $allowedMarketIds,
                ]);
            });
        });

        return redirect()->route('market-seeding.notifications')->with('success', 'Market seeding notification filters updated successfully.');
    }

    private function normalizeMarketIds(array $marketIds): array
    {
        return collect($marketIds)
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
