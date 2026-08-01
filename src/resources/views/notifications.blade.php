@extends('web::layouts.grids.12')

@section('title', 'Market Seeding Notifications')
@section('page_header', 'Market Seeding Notifications')

@section('content')
    @php
        $activeSkin = setting('skin') ?: 'default';
        $marketSeedingThemeClass = in_array($activeSkin, ['jet', 'iuligigi', 'gigigraphite'], true)
            ? 'market-seeding-dark-skin'
            : '';
        $alertLabels = [
            \Raikia\SeatMarketSeeding\Services\MarketStockTransitionNotifier::ALERT_LOW_STOCK => trans('seat-market-seeding::alerts.low_stock'),
            \Raikia\SeatMarketSeeding\Services\MarketStockTransitionNotifier::ALERT_EMPTY_STOCK => trans('seat-market-seeding::alerts.empty_stock'),
            \Raikia\SeatMarketSeeding\Services\MarketStockTransitionNotifier::ALERT_RESTOCKED => trans('seat-market-seeding::alerts.restocked'),
        ];
    @endphp

    <style>
        .market-seeding-notification-explainer {
            align-items: flex-start;
            background: linear-gradient(135deg, #f8fbfc 0%, #eef7fa 100%);
            border: 1px solid rgba(23, 162, 184, .22);
            border-left: 4px solid #17a2b8;
            border-radius: 8px;
            display: flex;
            gap: .8rem;
            margin-bottom: 1rem;
            padding: .9rem 1rem;
        }
        .market-seeding-notification-explainer i {
            color: #17a2b8;
            font-size: 1.25rem;
            margin-top: .12rem;
        }
        .market-seeding-notification-explainer strong {
            display: block;
            margin-bottom: .2rem;
        }
        .market-seeding-notification-explainer p {
            margin: 0;
        }
        .market-seeding-notification-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 1rem;
            overflow: hidden;
        }
        .market-seeding-notification-card-header {
            background: #f8f9fa;
            padding: 0;
        }
        .market-seeding-notification-toggle {
            align-items: flex-start;
            color: inherit;
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            justify-content: space-between;
            padding: .9rem 1rem;
            text-align: left;
            width: 100%;
        }
        .market-seeding-notification-toggle:hover,
        .market-seeding-notification-toggle:focus {
            color: inherit;
            text-decoration: none;
        }
        .market-seeding-notification-toggle-icon {
            color: #6c757d;
            margin-right: .45rem;
            transition: transform .15s ease;
        }
        .market-seeding-notification-toggle[aria-expanded="true"] .market-seeding-notification-toggle-icon {
            transform: rotate(90deg);
        }
        .market-seeding-notification-card-body {
            border-top: 1px solid #dee2e6;
            padding: .9rem 1rem;
        }
        .market-seeding-notification-card-title {
            font-size: 1.05rem;
            font-weight: 700;
            margin: 0;
        }
        .market-seeding-notification-card-meta {
            color: #6c757d;
            display: block;
            margin-top: .15rem;
        }
        .market-seeding-notification-alerts {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
            justify-content: flex-end;
        }
        .market-seeding-notification-card-body label {
            font-weight: 700;
        }
        .market-seeding-notification-card-body select {
            min-height: 8.5rem;
        }
        .market-seeding-notification-hint {
            color: #6c757d;
            display: block;
            margin-top: .35rem;
        }
        .market-seeding-notification-empty {
            border: 1px dashed #ced4da;
            border-radius: 8px;
            color: #6c757d;
            padding: 1.25rem;
            text-align: center;
        }
        .market-seeding-dark-skin .market-seeding-notification-card {
            background: #1f292e;
            border-color: #315766;
        }
        .market-seeding-dark-skin .market-seeding-notification-card-header {
            background: #1b252b;
        }
        .market-seeding-dark-skin .market-seeding-notification-card-body {
            border-color: #315766;
        }
        .market-seeding-dark-skin .market-seeding-notification-explainer {
            background: #1f292e;
            border-color: #315766;
        }
        .market-seeding-dark-skin .market-seeding-notification-card-meta,
        .market-seeding-dark-skin .market-seeding-notification-hint,
        .market-seeding-dark-skin .market-seeding-notification-explainer {
            color: #b8c7ce;
        }
        .market-seeding-dark-skin .market-seeding-notification-empty {
            border-color: #315766;
            color: #b8c7ce;
        }
    </style>

    <div class="market-seeding-notifications-shell {{ $marketSeedingThemeClass }}">
        @include('seat-market-seeding::partials.expiring-orders-alert')

        <div class="market-seeding-notification-explainer">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>Route market seeding alerts by market.</strong>
                <p>
                    These settings only affect SeAT notification groups that already have market seeding alerts enabled.
                    Pick the markets each group should receive. If a group has no markets selected, it receives alerts for every seeded market.
                </p>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('market-seeding.notifications.update') }}">
            @csrf

            @if($notificationGroups->isEmpty())
                <div class="market-seeding-notification-empty">
                    No SeAT notification groups currently have market seeding alerts enabled.
                </div>
            @else
                <div id="market-seeding-notification-groups">
                @foreach($notificationGroups as $group)
                    @php
                        $groupFilter = $notificationGroupFilters->get($group->id);
                        $allowedMarketIds = collect($groupFilter?->allowed_market_ids ?? [])
                            ->map(fn ($id) => (int) $id)
                            ->all();
                        $alerts = $group->alerts
                            ->pluck('alert')
                            ->filter(fn ($alert) => array_key_exists($alert, $alertLabels))
                            ->values();
                        $channels = $group->integrations
                            ->pluck('type')
                            ->unique()
                            ->sort()
                            ->values();
                        $selectedMarketSummary = empty($allowedMarketIds)
                            ? 'All markets'
                            : count($allowedMarketIds) . ' selected market' . (count($allowedMarketIds) === 1 ? '' : 's');
                        $collapseId = 'market-seeding-notification-group-' . $group->id;
                    @endphp

                    <div class="market-seeding-notification-card">
                        <div class="market-seeding-notification-card-header">
                            <button class="btn btn-link market-seeding-notification-toggle collapsed"
                                    type="button"
                                    data-toggle="collapse"
                                    data-target="#{{ $collapseId }}"
                                    aria-expanded="false"
                                    aria-controls="{{ $collapseId }}">
                                <div>
                                    <h4 class="market-seeding-notification-card-title">
                                        <i class="fas fa-chevron-right market-seeding-notification-toggle-icon"></i>
                                        {{ $group->name }}
                                    </h4>
                                    <small class="market-seeding-notification-card-meta">
                                        {{ $selectedMarketSummary }}
                                        &middot;
                                        {{ $channels->isEmpty() ? 'No delivery channels configured' : 'Channels: ' . $channels->implode(', ') }}
                                    </small>
                                </div>
                                <div class="market-seeding-notification-alerts">
                                    @foreach($alerts as $alert)
                                        <span class="badge badge-info">{{ $alertLabels[$alert] }}</span>
                                    @endforeach
                                </div>
                            </button>
                        </div>
                        <div class="collapse" id="{{ $collapseId }}" data-parent="#market-seeding-notification-groups">
                            <div class="market-seeding-notification-card-body">
                                <input type="hidden" name="notification_group_filters[{{ $loop->index }}][notification_group_id]" value="{{ $group->id }}">

                                <div class="form-group mb-0">
                                    <label for="market-seeding-notification-group-{{ $group->id }}-markets">Allowed Markets</label>
                                    <select class="form-control"
                                            id="market-seeding-notification-group-{{ $group->id }}-markets"
                                            name="notification_group_filters[{{ $loop->index }}][allowed_market_ids][]"
                                            multiple>
                                        @foreach($markets as $market)
                                            <option value="{{ $market->id }}" {{ in_array((int) $market->id, $allowedMarketIds, true) ? 'selected' : '' }}>
                                                {{ $market->name }} - {{ $market->location_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="market-seeding-notification-hint">
                                        Leave every market unselected to allow this group to receive alerts for all markets.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
                </div>

                <div class="text-right">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Notification Filters
                    </button>
                </div>
            @endif
        </form>
    </div>
@endsection
