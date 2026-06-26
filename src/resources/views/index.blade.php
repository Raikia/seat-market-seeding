@extends('web::layouts.grids.12')

@section('title', 'Market Seeding')
@section('page_header', 'Market Seeding')

@section('content')
    @php
        $totals = $stockReport['totals'];
        $activeSkin = setting('skin') ?: 'default';
        $marketSeedingThemeClass = in_array($activeSkin, ['jet', 'iuligigi', 'gigigraphite'], true)
            ? 'market-seeding-dark-skin'
            : '';
        $isk = function ($value) {
            return '$' . number_format((float) $value, 2, '.', ',');
        };
        $whole = function ($value) {
            return number_format((float) $value, 0, '.', ',');
        };
        $volume = function ($value) {
            return number_format((float) $value, 2, '.', ',');
        };
        $percent = function ($value) {
            return number_format((float) $value, 1, '.', ',') . '%';
        };
        $singleMarket = count($stockReport['markets']) === 1;
    @endphp

    <style>
        .market-seeding-shell .info-box-number {
            font-size: 1.05rem;
            white-space: normal;
        }
        .market-seeding-summary {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            margin-left: 0;
            margin-right: 0;
        }
        .market-seeding-summary > div {
            padding-left: 0;
            padding-right: 0;
        }
        @media (min-width: 1400px) {
            .market-seeding-summary {
                grid-template-columns: repeat(5, minmax(0, 1fr));
            }
        }
        .market-seeding-controls {
            align-items: center;
            display: flex;
            gap: .75rem;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        .market-seeding-controls .form-control {
            max-width: 360px;
        }
        .market-seeding-controls .market-seeding-filter-group {
            display: flex;
            flex: 1 1 auto;
            flex-wrap: wrap;
            gap: .5rem;
        }
        .market-seeding-item-type {
            display: block;
            margin-left: 1.85rem;
        }
        .market-seeding-card .card-header {
            align-items: center;
            display: flex;
            gap: 1rem;
            justify-content: space-between;
        }
        .market-seeding-card .card-header > div:first-child {
            flex: 1 1 auto;
            min-width: 0;
        }
        .market-seeding-card .card-title {
            float: none;
        }
        .market-seeding-card .card-subtitle {
            display: block;
            margin-top: .2rem;
        }
        .market-seeding-refresh-status {
            display: block;
            margin-top: .2rem;
        }
        .market-seeding-card .card-tools {
            display: flex;
            flex: 0 0 auto;
            flex-wrap: wrap;
            gap: .35rem;
            justify-content: flex-end;
            margin-left: auto;
        }
        .market-seeding-metrics {
            display: grid;
            gap: .75rem;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            margin-bottom: 1rem;
        }
        .market-seeding-metric {
            border-left: 3px solid #007bff;
            background: #f8f9fa;
            padding: .55rem .7rem;
        }
        .market-seeding-metric span {
            color: #6c757d;
            display: block;
            font-size: .8rem;
            text-transform: uppercase;
        }
        .market-seeding-metric strong {
            display: block;
            font-size: 1rem;
        }
        .market-seeding-source-icons {
            display: inline-flex;
            gap: .25rem;
            margin-right: .35rem;
            vertical-align: middle;
        }
        .market-seeding-source-icon {
            align-items: center;
            border-radius: 999px;
            display: inline-flex;
            font-size: .72rem;
            height: 1.35rem;
            justify-content: center;
            width: 1.35rem;
        }
        .market-seeding-source-manual {
            background: rgba(0, 123, 255, .14);
            color: #0056b3;
        }
        .market-seeding-source-doctrine {
            background: rgba(40, 167, 69, .16);
            color: #1e7e34;
        }
        .market-seeding-health-badge {
            font-size: .8rem;
            margin-left: .35rem;
        }
        .market-seeding-dark-skin .market-seeding-metric {
            background: #1f2d3d;
            border-left-color: #3c8dbc;
            color: #e9ecef;
        }
        .market-seeding-dark-skin .market-seeding-source-manual {
            background: rgba(60, 141, 188, .28);
            color: #9fd3f2;
        }
        .market-seeding-dark-skin .market-seeding-source-doctrine {
            background: rgba(40, 167, 69, .28);
            color: #9be7ad;
        }
        .market-seeding-dark-skin .market-seeding-metric span,
        .market-seeding-dark-skin .text-muted {
            color: #b8c7ce !important;
        }
        .market-seeding-dark-skin .market-seeding-card .card-header,
        .market-seeding-dark-skin .market-seeding-card .card-body {
            background: #222d32;
            color: #e9ecef;
        }
        .market-seeding-dark-skin .market-seeding-card {
            border-color: #3c4b54;
        }
        .market-seeding-dark-skin .table {
            color: #e9ecef;
        }
        .market-seeding-dark-skin .table thead th,
        .market-seeding-dark-skin .table td {
            border-color: #3c4b54;
        }
        .market-seeding-modal.market-seeding-dark-skin .modal-content {
            background: #222d32;
            color: #e9ecef;
        }
        .market-seeding-modal.market-seeding-dark-skin .modal-header,
        .market-seeding-modal.market-seeding-dark-skin .modal-footer {
            border-color: #3c4b54;
        }
        .market-seeding-modal.market-seeding-dark-skin .close {
            color: #e9ecef;
            opacity: .85;
            text-shadow: none;
        }
        .market-seeding-modal.market-seeding-dark-skin textarea.form-control {
            background: #1f2d3d;
            border-color: #3c4b54;
            color: #e9ecef;
        }
        .market-seeding-dark-skin .table-warning,
        .market-seeding-dark-skin .table-warning > td {
            background: #5f4b1f;
            color: #fff3cd;
        }
        .market-seeding-table-shell .dataTables_wrapper {
            padding: .5rem .25rem 0;
        }
        .market-seeding-table-shell table.dataTable {
            margin-top: .5rem !important;
            margin-bottom: .75rem !important;
            width: 100% !important;
        }
        .market-seeding-table-shell .dataTables_length,
        .market-seeding-table-shell .dataTables_filter,
        .market-seeding-table-shell .dataTables_info,
        .market-seeding-table-shell .dataTables_paginate {
            font-size: .875rem;
        }
        .market-seeding-table-shell .dataTables_filter input,
        .market-seeding-table-shell .dataTables_length select {
            border: 1px solid #ced4da;
            border-radius: .25rem;
            padding: .25rem .5rem;
        }
        .market-seeding-dark-skin .market-seeding-table-shell .dataTables_info,
        .market-seeding-dark-skin .market-seeding-table-shell .dataTables_filter label,
        .market-seeding-dark-skin .market-seeding-table-shell .dataTables_length label {
            color: #b8c7ce;
        }
        .market-seeding-dark-skin .market-seeding-table-shell .dataTables_filter input,
        .market-seeding-dark-skin .market-seeding-table-shell .dataTables_length select,
        .market-seeding-dark-skin .market-seeding-table-shell .dataTables_length select option {
            background: #1f2d3d;
            border-color: #3c4b54;
            color: #e9ecef;
        }
    </style>

    <div class="market-seeding-shell {{ $marketSeedingThemeClass }}">
    <div class="row market-seeding-summary">
        <div>
            <div class="info-box">
                <span class="info-box-icon bg-info"><i class="fas fa-store"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Seeded Value</span>
                    <span class="info-box-number">{{ $isk($totals['seeded_value']) }}</span>
                </div>
            </div>
        </div>
        <div>
            <div class="info-box">
                <span class="info-box-icon bg-success"><i class="fas fa-bullseye"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Target Value</span>
                    <span class="info-box-number">{{ $isk($totals['desired_value']) }}</span>
                </div>
            </div>
        </div>
        <div>
            <div class="info-box">
                <span class="info-box-icon bg-warning"><i class="fas fa-shopping-cart"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Restock Cost</span>
                    <span class="info-box-number">{{ $isk($totals['restock_cost']) }}</span>
                </div>
            </div>
        </div>
        <div>
            <div class="info-box">
                <span class="info-box-icon bg-danger"><i class="fas fa-exclamation-triangle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Missing Lines</span>
                    <span class="info-box-number">{{ $whole($totals['missing_lines']) }}</span>
                </div>
            </div>
        </div>
        <div>
            <div class="info-box">
                <span class="info-box-icon bg-primary"><i class="fas fa-cubes"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Restock Volume</span>
                    <span class="info-box-number">{{ $volume($totals['restock_volume']) }} m&sup3;</span>
                </div>
            </div>
        </div>
    </div>

    @if(count($stockReport['markets']) > 0)
        <div class="market-seeding-controls">
            <div class="market-seeding-filter-group">
                <select class="form-control" id="market-seeding-market-filter">
                    <option value="all">All Markets</option>
                    @foreach($stockReport['markets'] as $marketReport)
                        <option value="{{ $marketReport['market']->id }}">{{ $marketReport['market']->name }}</option>
                    @endforeach
                </select>
                <select class="form-control" id="market-seeding-type-filter">
                    <option value="">All Categories</option>
                    @foreach(collect($stockReport['markets'])->flatMap(fn ($marketReport) => $marketReport['rows']->pluck('type_category'))->unique()->sort()->values() as $typeCategory)
                        <option value="{{ $typeCategory }}">{{ $typeCategory }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <button type="button" class="btn btn-default btn-sm" id="market-seeding-expand-all">Expand All</button>
                <button type="button" class="btn btn-default btn-sm" id="market-seeding-collapse-all">Collapse All</button>
            </div>
        </div>
    @endif

    <div id="market-seeding-accordion">
        @forelse($stockReport['markets'] as $index => $marketReport)
            @php
                $market = $marketReport['market'];
                $exportId = 'market-seeding-export-' . $market->id;
                $collapseId = 'market-seeding-market-' . $market->id;
                $startsExpanded = $singleMarket;
                $restockLines = $marketReport['rows']
                    ->filter(fn ($row) => $row['missing_quantity'] > 0)
                    ->map(function ($row) {
                        return [
                            'category' => $row['type_category'],
                            'line' => $row['export_line'],
                            'volume' => $row['restock_volume'],
                        ];
                    })
                    ->values();
                $restockCategories = $restockLines->pluck('category')->unique()->sort()->values();
            @endphp

            <div class="card mb-3 market-seeding-card" data-market-id="{{ $market->id }}">
                <div class="card-header">
                    <div>
                        <h3 class="card-title mb-0">
                            {{ $market->name }}
                            <small class="text-muted">({{ $market->location_name }})</small>
                            @php
                                $healthScore = $marketReport['totals']['health_score'] ?? 100;
                                $healthBadge = $healthScore >= 90 ? 'badge-success' : ($healthScore >= 60 ? 'badge-warning' : 'badge-danger');
                            @endphp
                            <span class="badge {{ $healthBadge }} market-seeding-health-badge">Health {{ $percent($healthScore) }}</span>
                        </h3>
                        <small class="text-muted card-subtitle">
                            Missing {{ $whole($marketReport['totals']['missing_lines']) }} line(s) &middot;
                            Restock {{ $isk($marketReport['totals']['restock_cost']) }} &middot;
                            {{ $volume($marketReport['totals']['restock_volume']) }} m&sup3;
                        </small>
                        <small class="text-muted market-seeding-refresh-status">
                            @if($market->last_refreshed_at)
                                @php
                                    $refreshBadge = [
                                        'success' => 'badge-success',
                                        'skipped' => 'badge-warning',
                                        'error' => 'badge-danger',
                                    ][$market->last_refresh_status] ?? 'badge-secondary';
                                @endphp
                                <span class="badge {{ $refreshBadge }}">{{ ucfirst($market->last_refresh_status ?: 'unknown') }}</span>
                                Refreshed {{ $market->last_refreshed_at->format('Y-m-d H:i') }}
                                &middot; {{ $whole($market->last_refresh_orders) }} order(s)
                                @if($market->last_refresh_message)
                                    &middot; {{ $market->last_refresh_message }}
                                @endif
                            @else
                                <span class="badge badge-secondary">Never refreshed</span>
                            @endif
                        </small>
                    </div>
                    <div class="card-tools">
                        <button type="button" class="btn btn-sm btn-default" data-toggle="collapse" data-target="#{{ $collapseId }}" aria-expanded="{{ $startsExpanded ? 'true' : 'false' }}" aria-controls="{{ $collapseId }}">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#{{ $exportId }}-modal">
                            <i class="fas fa-shopping-cart"></i> Restock List
                        </button>
                        <a href="{{ route('market-seeding.export', $market->id) }}" class="btn btn-sm btn-default">
                            <i class="fas fa-file-export"></i> Raw Export
                        </a>
                    </div>
                </div>
                <div id="{{ $collapseId }}" class="collapse {{ $startsExpanded ? 'show' : '' }}">
                    <div class="card-body">
                        <div class="market-seeding-metrics">
                            <div class="market-seeding-metric">
                                <span>Health</span>
                                <strong>{{ $percent($marketReport['totals']['health_score'] ?? 100) }}</strong>
                            </div>
                            <div class="market-seeding-metric">
                                <span>Seeded</span>
                                <strong>{{ $isk($marketReport['totals']['seeded_value']) }}</strong>
                            </div>
                            <div class="market-seeding-metric">
                                <span>Target</span>
                                <strong>{{ $isk($marketReport['totals']['desired_value']) }}</strong>
                            </div>
                            <div class="market-seeding-metric">
                                <span>Restock</span>
                                <strong>{{ $isk($marketReport['totals']['restock_cost']) }}</strong>
                            </div>
                            <div class="market-seeding-metric">
                                <span>Restock Volume</span>
                                <strong>{{ $volume($marketReport['totals']['restock_volume']) }} m&sup3;</strong>
                            </div>
                            <div class="market-seeding-metric">
                                <span>Missing</span>
                                <strong>{{ $whole($marketReport['totals']['missing_lines']) }} lines</strong>
                            </div>
                        </div>

                        <div class="table-responsive market-seeding-table-shell">
                            <table class="table table-sm table-hover market-seeding-dashboard-table" id="market-seeding-dashboard-table-{{ $market->id }}">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Category</th>
                                        <th class="text-right">Current</th>
                                        <th class="text-right">Target</th>
                                        <th class="text-right">Missing</th>
                                        <th class="text-right">Local Price</th>
                                        <th class="text-right">Jita Price</th>
                                        <th class="text-right">vs Jita</th>
                                        <th class="text-right">Restock Cost</th>
                                        <th class="text-right">Restock m&sup3;</th>
                                        <th class="text-right">Seeded Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($marketReport['rows'] as $row)
                                        <tr class="{{ $row['is_low'] ? 'table-warning' : '' }}" data-category="{{ $row['type_category'] }}">
                                            <td>
                                                @include('seat-market-seeding::partials.source-icons', ['sourceFlags' => $row['source_flags']])
                                                {{ $row['item']->type_name }}
                                                <span class="text-muted small market-seeding-item-type">{{ $row['type_category'] }}</span>
                                            </td>
                                            <td>{{ $row['type_category'] }}</td>
                                            <td class="text-right" data-order="{{ $row['current_quantity'] }}">{{ $whole($row['current_quantity']) }}</td>
                                            <td class="text-right" data-order="{{ $row['item']->desired_quantity }}">{{ $whole($row['item']->desired_quantity) }}</td>
                                            <td class="text-right" data-order="{{ $row['missing_quantity'] }}">
                                                @if($row['missing_quantity'] > 0)
                                                    <span class="badge badge-danger">{{ $whole($row['missing_quantity']) }}</span>
                                                @else
                                                    <span class="badge badge-success">0</span>
                                                @endif
                                            </td>
                                            <td class="text-right" data-order="{{ $row['local_price'] ?: 0 }}">{{ $row['local_price'] ? $isk($row['local_price']) : '-' }}</td>
                                            <td class="text-right" data-order="{{ $row['jita_price'] ?: 0 }}">{{ $row['jita_price'] ? $isk($row['jita_price']) : '-' }}</td>
                                            <td class="text-right" data-order="{{ $row['price_delta'] ?? 0 }}">
                                                @if($row['price_delta'] !== null)
                                                    {{ $percent($row['price_delta']) }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="text-right" data-order="{{ $row['restock_cost'] }}">{{ $isk($row['restock_cost']) }}</td>
                                            <td class="text-right" data-order="{{ $row['restock_volume'] }}">{{ $volume($row['restock_volume']) }}</td>
                                            <td class="text-right" data-order="{{ $row['seeded_value'] }}">{{ $isk($row['seeded_value']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade market-seeding-modal {{ $marketSeedingThemeClass }}" id="{{ $exportId }}-modal" tabindex="-1" role="dialog" aria-labelledby="{{ $exportId }}-modal-label" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="{{ $exportId }}-modal-label">{{ $market->name }} Restock Multi-Buy</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted mb-2">
                                Estimated restock volume: <span class="market-seeding-export-volume" data-default-volume="{{ $marketReport['totals']['restock_volume'] }}">{{ $volume($marketReport['totals']['restock_volume']) }}</span> m&sup3;
                            </p>
                            <div class="form-group">
                                <label for="{{ $exportId }}-category">Category</label>
                                <select id="{{ $exportId }}-category" class="form-control market-seeding-export-category-filter" data-target="{{ $exportId }}">
                                    <option value="">All Categories</option>
                                    @foreach($restockCategories as $category)
                                        <option value="{{ $category }}">{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <textarea id="{{ $exportId }}" class="form-control market-seeding-export-textarea" rows="10" readonly data-lines='@json($restockLines)'>{{ $marketReport['export'] }}</textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary copy-market-export" data-target="{{ $exportId }}">
                                <i class="fas fa-copy"></i> Copy Multi-Buy
                            </button>
                            <a href="{{ route('market-seeding.export', $market->id) }}" class="btn btn-default">
                                <i class="fas fa-file-export"></i> Raw Export
                            </a>
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="alert alert-info">
                No seeded markets have been configured yet.
                @can('seat-market-seeding.manager')
                    <a href="{{ route('market-seeding.settings') }}">Create one in settings.</a>
                @endcan
            </div>
        @endforelse
    </div>
    </div>
@endsection

@push('javascript')
    <script>
        $(function () {
            var dashboardTables = null;

            if ($.fn.DataTable) {
                dashboardTables = $('.market-seeding-dashboard-table').DataTable({
                    order: [[0, 'asc']],
                    paging: true,
                    pageLength: 25,
                    lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
                    stateSave: true,
                    autoWidth: false,
                    columnDefs: [
                        { targets: [1], visible: false }
                    ],
                    stateSaveParams: function (settings, data) {
                        data.marketSeedingSchema = 2;
                    },
                    stateLoadParams: function (settings, data) {
                        return data.marketSeedingSchema === 2;
                    },
                    language: {
                        emptyTable: 'No stock targets have been configured for this market.',
                        zeroRecords: 'No items match this search.'
                    }
                });
            }

            $('.copy-market-export').on('click', function () {
                var textarea = document.getElementById($(this).data('target'));
                textarea.select();
                document.execCommand('copy');
            });

            $('.market-seeding-export-category-filter').on('change', function () {
                var category = $(this).val();
                var textarea = document.getElementById($(this).data('target'));
                var lines = $(textarea).data('lines') || [];
                var filtered = $.grep(lines, function (line) {
                    return !category || line.category === category;
                });
                var volume = filtered.reduce(function (total, line) {
                    return total + Number(line.volume || 0);
                }, 0);

                textarea.value = $.map(filtered, function (line) {
                    return line.line;
                }).join('\n');

                $(this).closest('.modal-body').find('.market-seeding-export-volume').text(formatDecimal(volume));
            });

            $('#market-seeding-market-filter').on('change', function () {
                var marketId = $(this).val();
                var cards = $('.market-seeding-card[data-market-id]');

                if (marketId === 'all') {
                    cards.show();
                    if (dashboardTables) {
                        dashboardTables.columns.adjust();
                    }
                    return;
                }

                cards.hide();
                var selected = $('.market-seeding-card[data-market-id="' + marketId + '"]');
                selected.show();
                selected.find('.collapse').collapse('show');
                if (dashboardTables) {
                    dashboardTables.columns.adjust();
                }
            });

            $('#market-seeding-type-filter').on('change', function () {
                applyDashboardTypeFilter($(this).val());
            });

            $('#market-seeding-expand-all').on('click', function () {
                $('#market-seeding-accordion .collapse').collapse('show');
            });

            $('#market-seeding-collapse-all').on('click', function () {
                $('#market-seeding-accordion .collapse').collapse('hide');
            });

            $('#market-seeding-accordion .collapse').on('shown.bs.collapse', function () {
                if (dashboardTables) {
                    dashboardTables.columns.adjust();
                }
            });

            function applyDashboardTypeFilter(typeCategory) {
                $('.market-seeding-dashboard-table').each(function () {
                    if (!$.fn.DataTable || !$.fn.DataTable.isDataTable(this)) {
                        $(this).find('tbody tr').each(function () {
                            var matches = !typeCategory || $(this).data('category') === typeCategory;
                            $(this).toggle(matches);
                        });
                        return;
                    }

                    $(this).DataTable()
                        .column(1)
                        .search(typeCategory ? '^' + escapeRegex(typeCategory) + '$' : '', true, false)
                        .draw();
                });
            }

            function escapeRegex(value) {
                return String(value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            }

            function formatDecimal(value) {
                return Number(value || 0).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }
        });
    </script>
@endpush
