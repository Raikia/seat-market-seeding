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
        $seederOrderPayload = [];
    @endphp

    @include('seat-market-seeding::partials.item-detail-modal-styles')
    @include('seat-market-seeding::partials.fit-review-styles')

    <style>
        .market-seeding-seeders-shell .seeders-intro {
            color: #6c757d;
            margin-bottom: 1.25rem;
        }
        .market-seeding-seeders-shell .seeders-market-card {
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
        .market-seeding-dark-skin .seeders-intro,
        .market-seeding-dark-skin .seeders-market-location,
        .market-seeding-dark-skin .seeders-market-stat span,
        .market-seeding-dark-skin .seeders-orders-summary-item span,
        .market-seeding-dark-skin .seeders-orders-subtitle,
        .market-seeding-dark-skin .seeders-person-meta,
        .market-seeding-dark-skin .seeders-empty,
        .market-seeding-dark-skin .market-seeding-table-shell .dataTables_info,
        .market-seeding-dark-skin .market-seeding-table-shell .dataTables_filter label,
        .market-seeding-dark-skin .market-seeding-table-shell .dataTables_length label {
            color: #b8c7ce;
        }
        .market-seeding-dark-skin .seeders-market-stat,
        .market-seeding-dark-skin .seeders-orders-summary-item,
        .market-seeding-dark-skin .seeders-empty {
            border-color: #3c4b54;
        }
        .market-seeding-dark-skin .seeders-share-bar {
            background: #34464f;
        }
        .market-seeding-dark-skin .seeders-person img {
            border-color: rgba(244, 231, 190, .18);
        }
        .market-seeding-dark-skin .seeders-order-item-main img {
            border-color: rgba(244, 231, 190, .18);
        }
        .market-seeding-dark-skin .market-seeding-table-shell .dataTables_filter input,
        .market-seeding-dark-skin .market-seeding-table-shell .dataTables_length select,
        .market-seeding-dark-skin .market-seeding-table-shell .dataTables_length select option {
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
            Main characters ranked by the value of active character-owned sell orders for tracked items at each seeded market.
            Values use remaining order quantity multiplied by the listed price.
        </p>

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
                                <span>Orders</span>
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
                    </div>
                </div>
                <div class="card-body market-seeding-table-shell">
                    @if($rows->isEmpty())
                        <div class="seeders-empty">
                            No active character-owned sell orders were found for tracked items at this market.
                        </div>
                    @else
                        <table class="table table-sm table-hover market-seeding-seeders-table">
                            <thead>
                                <tr>
                                    <th>Main Character</th>
                                    <th class="text-right">Listed Value</th>
                                    <th class="text-right">Market Share</th>
                                    <th class="text-right">Total m³</th>
                                    <th class="text-right">Orders</th>
                                    <th class="text-right">Tracked Types</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rows as $row)
                                    @php
                                        $orderKey = 'market-' . (int) $market->id . '-seeder-' . $loop->index;
                                        $seederOrderPayload[$orderKey] = [
                                            'name' => $row['main_character_name'],
                                            'market' => $market->name,
                                            'location' => $market->location_name,
                                            'listed_value' => $row['total_value'],
                                            'total_volume' => $row['total_volume'],
                                            'order_count' => $row['order_count'],
                                            'tracked_type_count' => $row['item_type_count'],
                                            'orders' => $row['orders'],
                                        ];
                                    @endphp
                                    <tr>
                                        <td data-order="{{ $row['main_character_name'] }}">
                                            <button type="button" class="seeders-person-button js-seeders-orders" data-orders-key="{{ $orderKey }}">
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
                            <h4 class="modal-title" id="seedersOrdersModalTitle">Seeder Orders</h4>
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
                                <span>Orders</span>
                                <strong id="seedersOrdersCount">0</strong>
                            </div>
                            <div class="seeders-orders-summary-item">
                                <span>Tracked Types</span>
                                <strong id="seedersOrdersTypes">0</strong>
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

            var seederOrders = @json($seederOrderPayload);
            var activeSeederOrders = [];
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

            $('.js-seeders-orders').on('click', function () {
                var payload = seederOrders[$(this).data('orders-key')];

                if (!payload) {
                    return;
                }

                var $table = $('#seedersOrdersTable');

                if ($.fn.DataTable && $.fn.DataTable.isDataTable($table)) {
                    $table.DataTable().destroy();
                }

                $('#seedersOrdersModalTitle').text(payload.name + ' Orders');
                $('#seedersOrdersModalSubtitle').text(payload.market + ' - ' + payload.location);
                $('#seedersOrdersListedValue').text(formatIsk(payload.listed_value));
                $('#seedersOrdersVolume').text(formatVolume(payload.total_volume));
                $('#seedersOrdersCount').text(formatWhole(payload.order_count));
                $('#seedersOrdersTypes').text(formatWhole(payload.tracked_type_count));
                activeSeederOrders = payload.orders || [];
                $table.find('tbody').html(activeSeederOrders.map(function (order, index) {
                    var expires = order.expires_at ? escapeHtml(order.expires_at + ' (' + order.days_until_expiry + 'd)') : '-';
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
                        '<td data-order="' + Number(order.days_until_expiry || 0) + '">' + expires + '</td>' +
                    '</tr>';
                }).join(''));

                $('#seedersOrdersModal').modal('show');

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
            });

            $('#seedersOrdersTable').on('click', '.js-seeders-order-item-details', function () {
                var index = parseInt($(this).data('order-index'), 10);

                openSeederItemDetails(activeSeederOrders[index]);
            });
        });
    </script>
@endpush
