@extends('web::layouts.grids.12')

@section('title', 'Market Seeders')
@section('page_header', 'Market Seeders')

@section('content')
    @php
        $activeSkin = setting('skin') ?: 'default';
        $marketSeedingThemeClass = in_array($activeSkin, ['jet', 'iuligigi', 'gigigraphite'], true)
            ? 'market-seeding-dark-skin'
            : '';
        $isk = fn ($value) => number_format((float) $value, 2, '.', ',') . ' ISK';
        $whole = fn ($value) => number_format((float) $value, 0, '.', ',');
        $volume = fn ($value) => number_format((float) $value, 2, '.', ',') . ' m³';
        $percent = fn ($value) => number_format((float) $value, 1, '.', ',') . '%';
    @endphp

    @include('seat-market-seeding::partials.item-detail-modal-styles')
    @include('seat-market-seeding::partials.fit-review-styles')

    <style>
        .market-seeding-seeders-shell .seeders-intro {
            color: #6c757d;
            margin-bottom: 1.25rem;
        }
        .market-seeding-seeders-shell .seeders-market-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }
        .market-seeding-seeders-shell .seeders-market-heading {
            align-items: flex-start;
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            width: 100%;
        }
        .market-seeding-seeders-shell .seeders-market-actions {
            align-items: center;
            display: flex;
            flex: 0 0 auto;
        }
        .market-seeding-seeders-shell .seeders-market-title {
            font-size: 1.05rem;
            font-weight: 700;
            line-height: 1.2;
            margin: 0;
        }
        .market-seeding-seeders-shell .seeders-market-location {
            color: #6c757d;
            font-size: .9rem;
            margin-top: .25rem;
        }
        .market-seeding-seeders-shell .seeders-market-stats {
            display: grid;
            flex: 1 1 760px;
            gap: .75rem;
            grid-template-columns: repeat(4, minmax(165px, 1fr));
            max-width: 1020px;
        }
        .market-seeding-seeders-shell .seeders-market-stat {
            background: linear-gradient(135deg, #f8fbfd 0%, #edf4f8 100%);
            border: 1px solid #d7dde2;
            border-radius: 8px;
            padding: .7rem .9rem;
        }
        .market-seeding-seeders-shell .seeders-market-stat span {
            color: #6c757d;
            display: block;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .market-seeding-seeders-shell .seeders-market-stat strong {
            display: block;
            font-size: .92rem;
            line-height: 1.25;
            margin-top: .15rem;
        }
        .market-seeding-seeders-shell .seeders-empty {
            align-items: center;
            border: 1px dashed #d7dde2;
            border-radius: 8px;
            color: #6c757d;
            display: flex;
            justify-content: center;
            min-height: 96px;
            padding: 1.5rem;
            text-align: center;
        }
        .market-seeding-seeders-shell .seeders-person {
            align-items: center;
            display: flex;
            gap: .7rem;
            min-width: 260px;
        }
        .market-seeding-seeders-shell .seeders-person-button {
            background: transparent;
            border: 0;
            color: inherit;
            display: inline-flex;
            padding: 0;
            text-align: left;
            width: 100%;
        }
        .market-seeding-seeders-shell .seeders-person-button:hover .seeders-person-name,
        .market-seeding-seeders-shell .seeders-person-button:focus .seeders-person-name {
            color: #17a2b8;
            text-decoration: underline;
        }
        .market-seeding-seeders-shell .seeders-person-button:focus {
            outline: none;
        }
        .market-seeding-seeders-shell .seeders-person img {
            background: #111820;
            border: 1px solid rgba(0, 0, 0, .16);
            border-radius: 50%;
            height: 42px;
            object-fit: cover;
            width: 42px;
        }
        .market-seeding-seeders-shell .seeders-person-name {
            font-weight: 700;
            line-height: 1.2;
        }
        .market-seeding-seeders-shell .seeders-person-meta {
            color: #6c757d;
            font-size: .78rem;
            line-height: 1.35;
            margin-top: .15rem;
        }
        .market-seeding-seeders-shell .seeders-expiring-pill {
            background: rgba(220, 53, 69, .12);
            border: 1px solid rgba(220, 53, 69, .45);
            border-radius: 999px;
            color: #dc3545;
            display: inline-flex;
            font-size: .68rem;
            font-weight: 800;
            gap: .25rem;
            letter-spacing: .02em;
            margin-top: .35rem;
            padding: .15rem .45rem;
            text-transform: uppercase;
        }
        .market-seeding-seeders-shell .seeders-share {
            min-width: 105px;
        }
        .market-seeding-seeders-shell .seeders-share-bar {
            background: #e9ecef;
            border-radius: 999px;
            height: 6px;
            margin-top: .3rem;
            overflow: hidden;
        }
        .market-seeding-seeders-shell .seeders-share-fill {
            background: #17a2b8;
            height: 100%;
        }
        .market-seeding-seeders-shell .seeders-orders-modal .modal-dialog {
            max-width: calc(100vw - 40px);
            width: 1180px;
        }
        .market-seeding-seeders-shell .seeders-orders-modal .modal-header {
            align-items: flex-start;
            display: flex;
            gap: 1rem;
            justify-content: space-between;
        }
        .market-seeding-seeders-shell .seeders-orders-modal .modal-header .close {
            float: none;
            margin-left: auto;
            order: 2;
        }
        .market-seeding-seeders-shell .seeders-orders-title-wrap {
            min-width: 0;
        }
        .market-seeding-seeders-shell .seeders-orders-modal .modal-title {
            line-height: 1.2;
            margin: 0;
        }
        .market-seeding-seeders-shell .seeders-orders-subtitle {
            color: #6c757d;
            font-size: .9rem;
            margin: .25rem 0 0;
        }
        .market-seeding-seeders-shell .seeders-orders-summary {
            display: grid;
            gap: .6rem;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            margin: 1rem 0;
        }
        .market-seeding-seeders-shell .seeders-orders-summary-item {
            background: linear-gradient(135deg, #f8fbfd 0%, #edf4f8 100%);
            border: 1px solid #d7dde2;
            border-radius: 8px;
            padding: .65rem .8rem;
        }
        .market-seeding-seeders-shell .seeders-orders-summary-item span {
            color: #6c757d;
            display: block;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .market-seeding-seeders-shell .seeders-orders-summary-item strong {
            display: block;
            margin-top: .15rem;
            overflow-wrap: anywhere;
        }
        .market-seeding-seeders-shell .seeders-orders-chart-panel {
            background: linear-gradient(135deg, #f8fbfd 0%, #edf4f8 100%);
            border: 1px solid #d7dde2;
            border-radius: 8px;
            margin-bottom: 1rem;
            padding: .85rem 1rem 1rem;
        }
        .market-seeding-seeders-shell .seeders-orders-chart-header {
            align-items: baseline;
            display: flex;
            gap: .75rem;
            justify-content: space-between;
            margin-bottom: .65rem;
        }
        .market-seeding-seeders-shell .seeders-orders-chart-header h5 {
            font-size: .85rem;
            font-weight: 800;
            letter-spacing: .04em;
            margin: 0;
            text-transform: uppercase;
        }
        .market-seeding-seeders-shell .seeders-orders-chart-header span {
            color: #6c757d;
            font-size: .78rem;
        }
        .market-seeding-seeders-shell .seeders-orders-chart-wrap {
            height: 220px;
            position: relative;
        }
        .market-seeding-seeders-shell .seeders-order-item-cell {
            align-items: center;
            display: flex;
            gap: .75rem;
            justify-content: space-between;
            min-width: 310px;
        }
        .market-seeding-seeders-shell .seeders-order-item-main {
            align-items: center;
            display: flex;
            gap: .6rem;
            min-width: 0;
        }
        .market-seeding-seeders-shell .seeders-order-item-main img {
            background: #111820;
            border: 1px solid rgba(0, 0, 0, .16);
            border-radius: 6px;
            flex: 0 0 32px;
            height: 32px;
            object-fit: cover;
            width: 32px;
        }
        .market-seeding-seeders-shell .seeders-order-item-name {
            font-weight: 700;
            line-height: 1.2;
            overflow-wrap: anywhere;
        }
        .market-seeding-seeders-shell .seeders-order-item-action {
            color: #fd7e14;
            flex: 0 0 auto;
            font-size: 1rem;
            padding: .1rem .25rem;
        }
        .market-seeding-seeders-shell .seeders-order-item-action:hover,
        .market-seeding-seeders-shell .seeders-order-item-action:focus {
            color: #ff9f43;
            text-decoration: none;
        }
        .market-seeding-seeders-shell .seeders-order-expires-soon {
            color: #dc3545;
            font-weight: 800;
        }
        .market-seeding-seeders-shell .market-seeding-table-shell .dataTables_wrapper {
            padding: .5rem .25rem 0;
        }
        .market-seeding-seeders-shell .market-seeding-table-shell table.dataTable {
            margin-top: .5rem !important;
            margin-bottom: .75rem !important;
            width: 100% !important;
        }
        .market-seeding-seeders-shell .market-seeding-table-shell .dataTables_length,
        .market-seeding-seeders-shell .market-seeding-table-shell .dataTables_filter,
        .market-seeding-seeders-shell .market-seeding-table-shell .dataTables_info,
        .market-seeding-seeders-shell .market-seeding-table-shell .dataTables_paginate {
            font-size: .875rem;
        }
        .market-seeding-seeders-shell .market-seeding-table-shell .dataTables_filter input,
        .market-seeding-seeders-shell .market-seeding-table-shell .dataTables_length select {
            border: 1px solid #ced4da;
            border-radius: .25rem;
            padding: .25rem .5rem;
        }
        .market-seeding-seeders-shell .seeders-orders-modal .dataTables_wrapper {
            padding-top: .25rem;
        }
        .market-seeding-seeders-shell .seeders-orders-modal .dataTables_length,
        .market-seeding-seeders-shell .seeders-orders-modal .dataTables_filter,
        .market-seeding-seeders-shell .seeders-orders-modal .dataTables_info,
        .market-seeding-seeders-shell .seeders-orders-modal .dataTables_paginate {
            font-size: .875rem;
        }
        .market-seeding-seeders-shell .seeders-orders-modal .dataTables_filter input,
        .market-seeding-seeders-shell .seeders-orders-modal .dataTables_length select {
            border: 1px solid #ced4da;
            border-radius: .25rem;
            padding: .25rem .5rem;
        }
        .market-seeding-dark-skin .seeders-intro,
        .market-seeding-dark-skin .seeders-market-location,
        .market-seeding-dark-skin .seeders-market-stat span,
        .market-seeding-dark-skin .seeders-orders-summary-item span,
        .market-seeding-dark-skin .seeders-orders-subtitle,
        .market-seeding-dark-skin .seeders-person-meta,
        .market-seeding-dark-skin .seeders-empty,
        .market-seeding-dark-skin .market-seeding-table-shell .dataTables_info,
        .market-seeding-dark-skin .market-seeding-table-shell .dataTables_filter label,
        .market-seeding-dark-skin .market-seeding-table-shell .dataTables_length label,
        .market-seeding-dark-skin .seeders-orders-modal .dataTables_info,
        .market-seeding-dark-skin .seeders-orders-modal .dataTables_filter label,
        .market-seeding-dark-skin .seeders-orders-modal .dataTables_length label {
            color: #b8c7ce;
        }
        .market-seeding-dark-skin .seeders-market-card,
        .market-seeding-dark-skin .seeders-market-card .card-header,
        .market-seeding-dark-skin .seeders-market-card .card-body {
            background: #222d32;
            border-color: #3c4b54;
            color: #e9ecef;
        }
        .market-seeding-dark-skin .seeders-market-card .card-header {
            border-bottom-color: #3c4b54;
        }
        .market-seeding-dark-skin .seeders-market-title,
        .market-seeding-dark-skin .seeders-market-stat strong,
        .market-seeding-dark-skin .seeders-orders-summary-item strong,
        .market-seeding-dark-skin .seeders-person-name,
        .market-seeding-dark-skin .seeders-order-item-name {
            color: #f4e7be;
        }
        .market-seeding-dark-skin .seeders-market-stat,
        .market-seeding-dark-skin .seeders-orders-summary-item,
        .market-seeding-dark-skin .seeders-orders-chart-panel,
        .market-seeding-dark-skin .seeders-empty {
            background: #1f292e;
            border-color: #3c4b54;
        }
        .market-seeding-dark-skin .seeders-orders-chart-header h5 {
            color: #f4e7be;
        }
        .market-seeding-dark-skin .seeders-orders-chart-header span {
            color: #b8c7ce;
        }
        .market-seeding-dark-skin .seeders-market-actions .btn-default {
            background: #756f6c;
            border-color: #756f6c;
            color: #f4e7be;
        }
        .market-seeding-dark-skin .seeders-market-actions .btn-default:hover,
        .market-seeding-dark-skin .seeders-market-actions .btn-default:focus {
            background: #867f7b;
            border-color: #867f7b;
            color: #fff7d6;
        }
        .market-seeding-dark-skin .seeders-share-bar {
            background: #34464f;
        }
        .market-seeding-dark-skin .seeders-share-fill {
            background: #7bdff2;
        }
        .market-seeding-dark-skin .seeders-person img {
            border-color: rgba(244, 231, 190, .18);
        }
        .market-seeding-dark-skin .seeders-order-item-main img {
            border-color: rgba(244, 231, 190, .18);
        }
        .market-seeding-dark-skin .seeders-expiring-pill {
            background: rgba(220, 53, 69, .2);
            border-color: rgba(255, 107, 107, .55);
            color: #ff9aa2;
        }
        .market-seeding-dark-skin .seeders-order-expires-soon {
            color: #ff9aa2;
        }
        .market-seeding-dark-skin .seeders-orders-modal .modal-content {
            background: #2f2927;
            color: #f4e7be;
        }
        .market-seeding-dark-skin .seeders-orders-modal .modal-header {
            border-bottom-color: rgba(244, 231, 190, .24);
        }
        .market-seeding-dark-skin .seeders-orders-modal .modal-header .close {
            color: #f4e7be;
            opacity: .75;
            text-shadow: none;
        }
        .market-seeding-dark-skin .seeders-orders-modal .table {
            color: #f4e7be;
        }
        .market-seeding-dark-skin .seeders-orders-modal .table thead th,
        .market-seeding-dark-skin .seeders-orders-modal .table td {
            border-color: rgba(244, 231, 190, .24);
        }
        .market-seeding-dark-skin .market-seeding-table-shell .dataTables_filter input,
        .market-seeding-dark-skin .market-seeding-table-shell .dataTables_length select,
        .market-seeding-dark-skin .market-seeding-table-shell .dataTables_length select option,
        .market-seeding-dark-skin .seeders-orders-modal .dataTables_filter input,
        .market-seeding-dark-skin .seeders-orders-modal .dataTables_length select,
        .market-seeding-dark-skin .seeders-orders-modal .dataTables_length select option {
            background: #1f2d3d;
            border-color: #3c4b54;
            color: #e9ecef;
        }
        .market-seeding-dark-skin .table {
            color: #e9ecef;
        }
        .market-seeding-dark-skin .table thead th,
        .market-seeding-dark-skin .table td {
            border-color: #3c4b54;
        }
        @media (max-width: 991.98px) {
            .market-seeding-seeders-shell .seeders-market-heading {
                display: block;
            }
            .market-seeding-seeders-shell .seeders-market-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                margin-top: .85rem;
                max-width: none;
            }
            .market-seeding-seeders-shell .seeders-orders-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>

    <div class="market-seeding-seeders-shell {{ $marketSeedingThemeClass }}">
        @include('seat-market-seeding::partials.expiring-orders-alert')

        <p class="seeders-intro">
            Main characters ranked by the value of active character-owned sell orders for items tracked by this plugin at each seeded market.
            Values use remaining order quantity multiplied by the listed price.
        </p>

        @php
            $singleMarket = $markets->count() === 1;
        @endphp

        @forelse($markets as $market)
            @php
                $leaderboard = $leaderboards[(int) $market->id] ?? [
                    'total_value' => 0,
                    'total_volume' => 0,
                    'total_orders' => 0,
                    'total_seeders' => 0,
                    'rows' => collect(),
                ];
                $rows = collect($leaderboard['rows']);
                $collapseId = 'market-seeding-seeders-market-' . $market->id;
                $startsExpanded = $singleMarket;
            @endphp
            <div class="card seeders-market-card">
                <div class="card-header">
                    <div class="seeders-market-heading">
                        <div>
                            <h3 class="seeders-market-title">{{ $market->name }}</h3>
                            <div class="seeders-market-location">{{ $market->location_name }}</div>
                        </div>
                        <div class="seeders-market-stats">
                            <div class="seeders-market-stat">
                                <span>Seeders</span>
                                <strong>{{ $whole($leaderboard['total_seeders']) }}</strong>
                            </div>
                            <div class="seeders-market-stat">
                                <span>Tracked Orders</span>
                                <strong>{{ $whole($leaderboard['total_orders']) }}</strong>
                            </div>
                            <div class="seeders-market-stat">
                                <span>Listed Value</span>
                                <strong>{{ $isk($leaderboard['total_value']) }}</strong>
                            </div>
                            <div class="seeders-market-stat">
                                <span>Total m³</span>
                                <strong>{{ $volume($leaderboard['total_volume']) }}</strong>
                            </div>
                        </div>
                        <div class="seeders-market-actions">
                            <button type="button" class="btn btn-sm btn-default" data-toggle="collapse" data-target="#{{ $collapseId }}" aria-expanded="{{ $startsExpanded ? 'true' : 'false' }}" aria-controls="{{ $collapseId }}">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div id="{{ $collapseId }}" class="collapse {{ $startsExpanded ? 'show' : '' }}">
                    <div class="card-body market-seeding-table-shell">
                        @if($rows->isEmpty())
                            <div class="seeders-empty">
                                No active tracked character-owned sell orders were found at this market.
                            </div>
                        @else
                            <table class="table table-sm table-hover market-seeding-seeders-table">
                                <thead>
                                    <tr>
                                        <th>Main Character</th>
                                        <th class="text-right">Listed Value</th>
                                        <th class="text-right">Market Share</th>
                                        <th class="text-right">Total m³</th>
                                        <th class="text-right">Tracked Orders</th>
                                        <th class="text-right">Tracked Types</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rows as $row)
                                    <tr>
                                        <td data-order="{{ $row['main_character_name'] }}">
                                            <button
                                                type="button"
                                                class="seeders-person-button js-seeders-orders"
                                                data-orders-url="{{ route('market-seeding.seeders.orders', $market) }}"
                                                data-seeder-key="{{ $row['account_key'] }}"
                                                data-name="{{ $row['main_character_name'] }}"
                                                data-market="{{ $market->name }}"
                                                data-location="{{ $market->location_name }}"
                                                data-listed-value="{{ $row['total_value'] }}"
                                                data-total-volume="{{ $row['total_volume'] }}"
                                                data-order-count="{{ $row['order_count'] }}"
                                                data-tracked-type-count="{{ $row['item_type_count'] }}"
                                            >
                                                <div class="seeders-person">
                                                    <img src="{{ $row['portrait_url'] }}" alt="{{ $row['main_character_name'] }} portrait">
                                                    <div>
                                                        <div class="seeders-person-name">
                                                            {{ $row['main_character_name'] }}
                                                            <i class="fas fa-search-plus ml-1"></i>
                                                        </div>
                                                        <div class="seeders-person-meta">
                                                            {{ implode(', ', $row['characters']) }}
                                                        </div>
                                                        @if($row['has_expiring_orders'] ?? false)
                                                            <div>
                                                                <span class="seeders-expiring-pill" title="{{ $whole($row['expiring_order_count'] ?? 0) }} order{{ (int) ($row['expiring_order_count'] ?? 0) === 1 ? '' : 's' }} expiring within 14 days">
                                                                    <i class="fas fa-clock"></i> Order expiring soon
                                                                </span>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </button>
                                        </td>
                                        <td class="text-right" data-order="{{ $row['total_value'] }}">{{ $isk($row['total_value']) }}</td>
                                        <td class="text-right" data-order="{{ $row['market_share'] }}">
                                            <div class="seeders-share ml-auto">
                                                {{ $percent($row['market_share']) }}
                                                <div class="seeders-share-bar">
                                                    <div class="seeders-share-fill" style="width: {{ min(100, max(0, (float) $row['market_share'])) }}%;"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-right" data-order="{{ $row['total_volume'] }}">{{ $volume($row['total_volume']) }}</td>
                                        <td class="text-right" data-order="{{ $row['order_count'] }}">{{ $whole($row['order_count']) }}</td>
                                        <td class="text-right" data-order="{{ $row['item_type_count'] }}">{{ $whole($row['item_type_count']) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="alert alert-info mb-0">
                No seeded markets are visible to your account.
            </div>
        @endforelse

        <div class="modal fade seeders-orders-modal" id="seedersOrdersModal" tabindex="-1" role="dialog" aria-labelledby="seedersOrdersModalTitle">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <div class="seeders-orders-title-wrap">
                            <h4 class="modal-title" id="seedersOrdersModalTitle">Seeder Tracked Orders</h4>
                            <p class="seeders-orders-subtitle" id="seedersOrdersModalSubtitle"></p>
                        </div>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="seeders-orders-summary">
                            <div class="seeders-orders-summary-item">
                                <span>Listed Value</span>
                                <strong id="seedersOrdersListedValue">0.00 ISK</strong>
                            </div>
                            <div class="seeders-orders-summary-item">
                                <span>Total m³</span>
                                <strong id="seedersOrdersVolume">0.00 m³</strong>
                            </div>
                            <div class="seeders-orders-summary-item">
                                <span>Tracked Orders</span>
                                <strong id="seedersOrdersCount">0</strong>
                            </div>
                            <div class="seeders-orders-summary-item">
                                <span>Tracked Types</span>
                                <strong id="seedersOrdersTypes">0</strong>
                            </div>
                        </div>
                        <div class="seeders-orders-chart-panel">
                            <div class="seeders-orders-chart-header">
                                <h5>Orders Created Over Time</h5>
                                <span>Total order value by issue date</span>
                            </div>
                            <div class="seeders-orders-chart-wrap">
                                <canvas id="seedersOrdersCreatedChart"></canvas>
                            </div>
                        </div>
                        <table class="table table-sm table-hover" id="seedersOrdersTable">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Character</th>
                                    <th class="text-right">Remaining</th>
                                    <th class="text-right">Price</th>
                                    <th class="text-right">Listed Value</th>
                                    <th class="text-right">Total m³</th>
                                    <th>Expires</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @include('seat-market-seeding::partials.item-detail-modal', [
            'marketSeedingThemeClass' => $marketSeedingThemeClass,
            'canManageMarketSeeding' => false,
            'canViewMarketOrderOwners' => auth()->user()->can('seat-market-seeding.seeders'),
        ])
    </div>
@endsection

@push('javascript')
    @include('seat-market-seeding::partials.fit-review-scripts')
    <script>
        $(function () {
            @include('seat-market-seeding::partials.item-detail-modal-readonly-scripts')

            var activeSeederOrders = [];
            var seedersOrdersCreatedChart = null;
            var formatNumber = function (value, decimals) {
                return Number(value || 0).toLocaleString(undefined, {
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals
                });
            };
            var formatWhole = function (value) {
                return Number(value || 0).toLocaleString(undefined, {
                    maximumFractionDigits: 0
                });
            };
            var formatIsk = function (value) {
                return formatNumber(value, 2) + ' ISK';
            };
            var formatVolume = function (value) {
                return formatNumber(value, 2) + ' m³';
            };
            var compactIsk = function (value) {
                value = Number(value || 0);

                if (Math.abs(value) >= 1000000000000) {
                    return formatNumber(value / 1000000000000, 2) + 'T ISK';
                }

                if (Math.abs(value) >= 1000000000) {
                    return formatNumber(value / 1000000000, 2) + 'B ISK';
                }

                if (Math.abs(value) >= 1000000) {
                    return formatNumber(value / 1000000, 2) + 'M ISK';
                }

                return formatIsk(value);
            };
            var escapeHtml = function (value) {
                return $('<div>').text(value || '').html();
            };
            var typeIconUrl = function (typeId, size) {
                if (!typeId) {
                    return '';
                }

                return 'https://images.evetech.net/types/' + encodeURIComponent(typeId) + '/icon?size=' + (size || 32);
            };
            var openSeederItemDetails = function (order) {
                if (!order || !order.history_url) {
                    return;
                }

                var openDetails = function () {
                    resetItemDetails();
                    $('#market-seeding-edit-target-item').text(order.item_name || 'Item');
                    $('#market-seeding-edit-target-market').text(
                        (order.market_name || '') + (order.location_name ? ' - ' + order.location_name : '')
                    );
                    $('#market-seeding-edit-target-form')
                        .attr('action', '#')
                        .data('original-target-quantity', 0)
                        .data('original-warning-quantity', 0);
                    $('#market-seeding-edit-target-modal').modal('show');
                    loadItemDetails(order.history_url);
                };

                var $ordersModal = $('#seedersOrdersModal');

                if ($ordersModal.is(':visible')) {
                    $ordersModal.one('hidden.bs.modal', openDetails).modal('hide');
                    return;
                }

                openDetails();
            };
            var ordersCreatedSeries = function (orders) {
                var totals = {};

                (orders || []).forEach(function (order) {
                    if (!order.issued_at) {
                        return;
                    }

                    var date = String(order.issued_at).slice(0, 10);
                    var value = Number(order.created_value || 0);
                    totals[date] = (totals[date] || 0) + value;
                });

                return Object.keys(totals).sort().map(function (date) {
                    return {
                        date: date,
                        value: totals[date]
                    };
                });
            };
            var renderOrdersCreatedChart = function (orders) {
                var canvas = document.getElementById('seedersOrdersCreatedChart');

                if (seedersOrdersCreatedChart) {
                    seedersOrdersCreatedChart.destroy();
                    seedersOrdersCreatedChart = null;
                }

                if (!canvas || !window.Chart) {
                    return;
                }

                var series = ordersCreatedSeries(orders);
                var isDark = $('.market-seeding-seeders-shell').hasClass('market-seeding-dark-skin');
                var gridColor = isDark ? 'rgba(244, 231, 190, .14)' : 'rgba(0, 0, 0, .08)';
                var tickColor = isDark ? '#b8c7ce' : '#6c757d';

                seedersOrdersCreatedChart = new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: series.map(function (point) {
                            return point.date;
                        }),
                        datasets: [{
                            label: 'Created order value',
                            data: series.map(function (point) {
                                return point.value;
                            }),
                            backgroundColor: isDark ? 'rgba(123, 223, 242, .55)' : 'rgba(23, 162, 184, .45)',
                            borderColor: isDark ? '#7bdff2' : '#17a2b8',
                            lineTension: .25,
                            borderWidth: 1,
                            fill: true,
                            pointBackgroundColor: isDark ? '#7bdff2' : '#17a2b8',
                            pointBorderColor: isDark ? '#1f292e' : '#fff',
                            pointHoverRadius: 5,
                            pointRadius: 3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        legend: {
                            display: false
                        },
                        tooltips: {
                            callbacks: {
                                label: function (tooltipItem) {
                                    return 'Created: ' + formatIsk(tooltipItem.yLabel);
                                }
                            }
                        },
                        scales: {
                            xAxes: [{
                                gridLines: {
                                    color: gridColor
                                },
                                ticks: {
                                    fontColor: tickColor,
                                    maxRotation: 45,
                                    minRotation: 0
                                }
                            }],
                            yAxes: [{
                                gridLines: {
                                    color: gridColor
                                },
                                ticks: {
                                    beginAtZero: true,
                                    fontColor: tickColor,
                                    callback: function (value) {
                                        return compactIsk(value).replace(' ISK', '');
                                    }
                                }
                            }]
                        }
                    }
                });
            };
            var renderSeederOrdersTable = function ($table, orders) {
                activeSeederOrders = orders || [];
                $table.find('tbody').html(activeSeederOrders.map(function (order, index) {
                    var expires = order.expires_at ? escapeHtml(order.expires_at + ' (' + order.days_until_expiry + 'd)') : '-';
                    var expiresClass = order.expires_soon ? ' class="seeders-order-expires-soon"' : '';
                    var icon = typeIconUrl(order.type_id, 32);
                    var itemCell = '<div class="seeders-order-item-cell">' +
                        '<div class="seeders-order-item-main">' +
                            (icon ? '<img src="' + icon + '" alt="">' : '') +
                            '<div class="seeders-order-item-name">' + escapeHtml(order.item_name) + '</div>' +
                        '</div>' +
                        (order.history_url
                            ? '<button type="button" class="btn btn-link seeders-order-item-action js-seeders-order-item-details" data-order-index="' + index + '" title="View item details" aria-label="View item details for ' + escapeHtml(order.item_name) + '"><i class="fas fa-search"></i></button>'
                            : '') +
                    '</div>';

                    return '<tr>' +
                        '<td>' + itemCell + '</td>' +
                        '<td>' + escapeHtml(order.character_name) + '</td>' +
                        '<td class="text-right" data-order="' + Number(order.quantity_remaining || 0) + '">' + formatWhole(order.quantity_remaining) + ' / ' + formatWhole(order.quantity_total) + '</td>' +
                        '<td class="text-right" data-order="' + Number(order.price || 0) + '">' + formatIsk(order.price) + '</td>' +
                        '<td class="text-right" data-order="' + Number(order.listed_value || 0) + '">' + formatIsk(order.listed_value) + '</td>' +
                        '<td class="text-right" data-order="' + Number(order.total_volume || 0) + '">' + formatVolume(order.total_volume) + '</td>' +
                        '<td data-order="' + Number(order.days_until_expiry || 0) + '"' + expiresClass + '>' + expires + '</td>' +
                    '</tr>';
                }).join(''));

                if ($.fn.DataTable) {
                    $table.DataTable({
                        order: [[4, 'desc']],
                        pageLength: 10,
                        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
                        searching: true,
                        info: true,
                        autoWidth: false,
                        deferRender: true
                    });
                }
            };

            if ($.fn.DataTable) {
                $('.market-seeding-seeders-table').DataTable({
                    order: [[1, 'desc']],
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
                    searching: true,
                    info: true,
                    autoWidth: false,
                    deferRender: true,
                    language: {
                        emptyTable: 'No active tracked sell orders were found for this market.',
                        zeroRecords: 'No seeders match this search.'
                    }
                });
            }

            $('.market-seeding-seeders-shell .collapse').on('shown.bs.collapse', function () {
                if ($.fn.DataTable) {
                    $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
                }
            });

            $('.js-seeders-orders').on('click', function () {
                var $button = $(this);
                var $table = $('#seedersOrdersTable');

                if ($.fn.DataTable && $.fn.DataTable.isDataTable($table)) {
                    $table.DataTable().destroy();
                }

                activeSeederOrders = [];
                $('#seedersOrdersModalTitle').text($button.data('name') + ' Tracked Orders');
                $('#seedersOrdersModalSubtitle').text($button.data('market') + ' - ' + $button.data('location'));
                $('#seedersOrdersListedValue').text(formatIsk($button.data('listed-value')));
                $('#seedersOrdersVolume').text(formatVolume($button.data('total-volume')));
                $('#seedersOrdersCount').text(formatWhole($button.data('order-count')));
                $('#seedersOrdersTypes').text(formatWhole($button.data('tracked-type-count')));
                $table.find('tbody').html('<tr><td colspan="7" class="text-muted">Loading tracked orders...</td></tr>');

                $('#seedersOrdersModal')
                    .one('shown.bs.modal', function () {
                        renderOrdersCreatedChart(activeSeederOrders);
                    })
                    .modal('show');

                $.getJSON($button.data('orders-url'), {
                    seeder_key: $button.data('seeder-key')
                }).done(function (payload) {
                    if ($.fn.DataTable && $.fn.DataTable.isDataTable($table)) {
                        $table.DataTable().destroy();
                    }

                    $('#seedersOrdersListedValue').text(formatIsk(payload.listed_value));
                    $('#seedersOrdersVolume').text(formatVolume(payload.total_volume));
                    $('#seedersOrdersCount').text(formatWhole(payload.order_count));
                    $('#seedersOrdersTypes').text(formatWhole(payload.tracked_type_count));
                    renderSeederOrdersTable($table, payload.orders || []);
                    renderOrdersCreatedChart(activeSeederOrders);
                }).fail(function () {
                    $table.find('tbody').html('<tr><td colspan="7" class="text-danger">Could not load tracked orders for this seeder.</td></tr>');
                });
            });

            $('#seedersOrdersTable').on('click', '.js-seeders-order-item-details', function () {
                var index = parseInt($(this).data('order-index'), 10);

                openSeederItemDetails(activeSeederOrders[index]);
            });

            $('#seedersOrdersModal').on('hidden.bs.modal', function () {
                if (seedersOrdersCreatedChart) {
                    seedersOrdersCreatedChart.destroy();
                    seedersOrdersCreatedChart = null;
                }
            });
        });
    </script>
@endpush
