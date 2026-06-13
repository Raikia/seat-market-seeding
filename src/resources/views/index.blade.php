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
            return number_format((float) $value, 2, '.', ',') . ' ISK';
        };
        $whole = function ($value) {
            return number_format((float) $value, 0, '.', ',');
        };
        $percent = function ($value) {
            return number_format((float) $value, 1, '.', ',') . '%';
        };
    @endphp

    <style>
        .market-seeding-shell .info-box-number {
            font-size: 1.05rem;
            white-space: normal;
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
        .market-seeding-card .card-header {
            align-items: center;
            display: flex;
            justify-content: space-between;
        }
        .market-seeding-card .card-tools {
            display: flex;
            gap: .35rem;
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
        .market-seeding-dark-skin .market-seeding-metric {
            background: #1f2d3d;
            border-left-color: #3c8dbc;
            color: #e9ecef;
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
        <div class="col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-info"><i class="fas fa-store"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Seeded Value</span>
                    <span class="info-box-number">{{ $isk($totals['seeded_value']) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-success"><i class="fas fa-bullseye"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Target Value</span>
                    <span class="info-box-number">{{ $isk($totals['desired_value']) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-warning"><i class="fas fa-shopping-cart"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Restock Cost</span>
                    <span class="info-box-number">{{ $isk($totals['restock_cost']) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-danger"><i class="fas fa-exclamation-triangle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Missing Lines</span>
                    <span class="info-box-number">{{ $whole($totals['missing_lines']) }}</span>
                </div>
            </div>
        </div>
    </div>

    @if(count($stockReport['markets']) > 0)
        <div class="market-seeding-controls">
            <select class="form-control" id="market-seeding-market-filter">
                <option value="all">All Markets</option>
                @foreach($stockReport['markets'] as $marketReport)
                    <option value="{{ $marketReport['market']->id }}">{{ $marketReport['market']->name }}</option>
                @endforeach
            </select>
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
                $expanded = $index === 0;
            @endphp

            <div class="card mb-3 market-seeding-card" data-market-id="{{ $market->id }}">
                <div class="card-header">
                    <div>
                        <h3 class="card-title mb-0">
                            {{ $market->name }}
                            <small class="text-muted">({{ $market->location_name }})</small>
                        </h3>
                        <small class="text-muted">
                            Missing {{ $whole($marketReport['totals']['missing_lines']) }} line(s) &middot;
                            Restock {{ $isk($marketReport['totals']['restock_cost']) }}
                        </small>
                    </div>
                    <div class="card-tools">
                        <button type="button" class="btn btn-sm btn-default" data-toggle="collapse" data-target="#{{ $collapseId }}" aria-expanded="{{ $expanded ? 'true' : 'false' }}" aria-controls="{{ $collapseId }}">
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
                <div id="{{ $collapseId }}" class="collapse {{ $expanded ? 'show' : '' }}">
                    <div class="card-body">
                        <div class="market-seeding-metrics">
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
                                <span>Missing</span>
                                <strong>{{ $whole($marketReport['totals']['missing_lines']) }} lines</strong>
                            </div>
                        </div>

                        <div class="table-responsive market-seeding-table-shell">
                            <table class="table table-sm table-hover market-seeding-dashboard-table" id="market-seeding-dashboard-table-{{ $market->id }}">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th class="text-right">Current</th>
                                        <th class="text-right">Target</th>
                                        <th class="text-right">Missing</th>
                                        <th class="text-right">Local Price</th>
                                        <th class="text-right">Jita Price</th>
                                        <th class="text-right">vs Jita</th>
                                        <th class="text-right">Restock Cost</th>
                                        <th class="text-right">Seeded Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($marketReport['rows'] as $row)
                                        <tr class="{{ $row['is_low'] ? 'table-warning' : '' }}">
                                            <td>{{ $row['item']->type_name }}</td>
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
                            <textarea id="{{ $exportId }}" class="form-control" rows="10" readonly>{{ $marketReport['export'] }}</textarea>
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
                    order: [[3, 'desc'], [0, 'asc']],
                    paging: true,
                    pageLength: 25,
                    lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
                    stateSave: true,
                    autoWidth: false,
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
        });
    </script>
@endpush
