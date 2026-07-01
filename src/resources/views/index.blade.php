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
        $volume = function ($value) {
            return number_format((float) $value, 2, '.', ',');
        };
        $percent = function ($value) {
            return number_format((float) $value, 1, '.', ',') . '%';
        };
        $singleMarket = count($stockReport['markets']) === 1;
        $stockRows = collect($stockReport['markets'])->flatMap(fn ($marketReport) => $marketReport['rows']);
        $typeCategories = $stockRows->pluck('type_category')->unique()->sort()->values();
        $typeGroups = $stockRows
            ->map(fn ($row) => ['category' => $row['type_category'], 'group' => $row['type_group'] ?? 'Unknown'])
            ->unique(fn ($row) => $row['category'] . '|' . $row['group'])
            ->sortBy('group')
            ->values();
        $dashboardItemDetails = [];
        foreach ($stockReport['markets'] as $marketReport) {
            $market = $marketReport['market'];

            foreach ($marketReport['rows'] as $row) {
                $item = $row['item'];
                $dashboardItemDetails[$item->id] = [
                    'item_id' => $item->id,
                    'market_id' => $market->id,
                    'history_url' => route('market-seeding.items.history', $item->id),
                    'item_name' => $item->type_name,
                    'market_name' => $market->name . ' - ' . $market->location_name,
                    'desired_quantity' => $item->desired_quantity,
                    'warning_quantity' => $item->warning_quantity,
                ];
            }
        }
    @endphp

    @include('seat-market-seeding::partials.item-detail-modal-styles')

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
            align-items: flex-start;
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
        .market-seeding-filter-card {
            background: linear-gradient(180deg, #fbfcfe 0%, #f4f7fb 100%);
            border: 1px solid rgba(31, 73, 103, .12);
            border-radius: .65rem;
            box-shadow: 0 8px 20px rgba(24, 50, 71, .05);
            flex: 1 1 auto;
            max-width: 940px;
            padding: .75rem .85rem .85rem;
        }
        .market-seeding-filter-header {
            align-items: center;
            display: flex;
            gap: .75rem;
            justify-content: space-between;
        }
        .market-seeding-filter-heading {
            align-items: center;
            display: flex;
            gap: .5rem;
            min-width: 0;
        }
        .market-seeding-filter-heading i {
            align-items: center;
            background: rgba(0, 123, 255, .12);
            border-radius: 999px;
            color: #007bff;
            display: inline-flex;
            flex: 0 0 auto;
            height: 1.85rem;
            justify-content: center;
            width: 1.85rem;
        }
        .market-seeding-filter-heading strong {
            color: #183247;
            display: block;
            line-height: 1.1;
        }
        .market-seeding-filter-heading small {
            display: block;
            line-height: 1.2;
            margin-top: .1rem;
        }
        .market-seeding-filter-fields {
            display: flex;
            flex-wrap: wrap;
            gap: .65rem;
            padding-top: .65rem;
        }
        .market-seeding-filter-field {
            flex: 0 1 165px;
            min-width: 150px;
        }
        .market-seeding-filter-field label {
            color: #54657a;
            display: block;
            font-size: .78rem;
            font-weight: 600;
            letter-spacing: .01em;
            margin-bottom: .3rem;
        }
        .market-seeding-filter-card .form-control {
            max-width: none;
        }
        .market-seeding-filter-actions {
            display: flex;
            justify-content: flex-end;
        }
        .market-seeding-controls-actions {
            align-items: center;
            display: flex;
            flex: 0 0 auto;
            gap: .35rem;
        }
        @media (max-width: 1199px) {
            .market-seeding-controls {
                flex-direction: column;
            }
            .market-seeding-controls-actions {
                justify-content: flex-end;
            }
        }
        .market-seeding-item-type {
            display: block;
            margin-left: 1.85rem;
        }
        .market-seeding-view-item {
            color: #6c757d;
            margin-left: .35rem;
            vertical-align: middle;
        }
        .market-seeding-view-item:hover,
        .market-seeding-view-item:focus {
            color: #007bff;
            text-decoration: none;
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
        .market-seeding-metric > span {
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
        .market-seeding-dark-skin .market-seeding-filter-card {
            background: linear-gradient(180deg, #22313a 0%, #1f2d33 100%);
            border-color: rgba(60, 141, 188, .28);
            box-shadow: 0 1px 4px rgba(0, 0, 0, .25);
        }
        .market-seeding-dark-skin .market-seeding-filter-heading strong {
            color: #e9ecef;
        }
        .market-seeding-dark-skin .market-seeding-filter-heading i {
            background: rgba(60, 141, 188, .25);
            color: #9fd3f2;
        }
        .market-seeding-dark-skin .market-seeding-filter-field label {
            color: #b8c7ce;
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
            <div class="market-seeding-filter-card">
                <div class="market-seeding-filter-header">
                    <div class="market-seeding-filter-heading">
                        <i class="fas fa-sliders-h"></i>
                        <div>
                            <strong>Filters</strong>
                            <small class="text-muted">Narrow market rows and restock exports.</small>
                        </div>
                    </div>
                    <div class="market-seeding-filter-actions">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="market-seeding-toggle-filters" data-toggle="collapse" data-target="#market-seeding-filter-body" aria-expanded="false" aria-controls="market-seeding-filter-body">
                            <i class="fas fa-sliders-h"></i> Show Filters
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="market-seeding-reset-filters">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                    </div>
                </div>
                <div class="collapse" id="market-seeding-filter-body">
                    <div class="market-seeding-filter-fields">
                        <div class="market-seeding-filter-field">
                            <label for="market-seeding-market-filter">Market</label>
                            <select class="form-control form-control-sm" id="market-seeding-market-filter">
                                <option value="all">All Markets</option>
                                @foreach($stockReport['markets'] as $marketReport)
                                    <option value="{{ $marketReport['market']->id }}">{{ $marketReport['market']->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="market-seeding-filter-field">
                            <label for="market-seeding-type-filter">Category</label>
                            <select class="form-control form-control-sm" id="market-seeding-type-filter">
                                <option value="">All Categories</option>
                                @foreach($typeCategories as $typeCategory)
                                    <option value="{{ $typeCategory }}">{{ $typeCategory }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="market-seeding-filter-field">
                            <label for="market-seeding-group-filter">Group</label>
                            <select class="form-control form-control-sm" id="market-seeding-group-filter">
                                <option value="">All Groups</option>
                                @foreach($typeGroups as $typeGroup)
                                    <option value="{{ $typeGroup['group'] }}" data-category="{{ $typeGroup['category'] }}">{{ $typeGroup['group'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="market-seeding-controls-actions">
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
                            'group' => $row['type_group'] ?? 'Unknown',
                            'line' => $row['export_line'],
                            'volume' => $row['restock_volume'],
                        ];
                    })
                    ->values();
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
                            <span class="badge {{ $healthBadge }} market-seeding-health-badge">Health <span data-market-metric="header-health">{{ $percent($healthScore) }}</span></span>
                        </h3>
                        <small class="text-muted card-subtitle">
                            Missing <span data-market-metric="header-missing">{{ $whole($marketReport['totals']['missing_lines']) }}</span> line(s) &middot;
                            Restock <span data-market-metric="header-restock">{{ $isk($marketReport['totals']['restock_cost']) }}</span> &middot;
                            <span data-market-metric="header-restock-volume">{{ $volume($marketReport['totals']['restock_volume']) }}</span> m&sup3;
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
                                <strong data-market-metric="health">{{ $percent($marketReport['totals']['health_score'] ?? 100) }}</strong>
                            </div>
                            <div class="market-seeding-metric">
                                <span>Seeded</span>
                                <strong data-market-metric="seeded">{{ $isk($marketReport['totals']['seeded_value']) }}</strong>
                            </div>
                            <div class="market-seeding-metric">
                                <span>Target</span>
                                <strong data-market-metric="target">{{ $isk($marketReport['totals']['desired_value']) }}</strong>
                            </div>
                            <div class="market-seeding-metric">
                                <span>Restock</span>
                                <strong data-market-metric="restock">{{ $isk($marketReport['totals']['restock_cost']) }}</strong>
                            </div>
                            <div class="market-seeding-metric">
                                <span>Restock Volume</span>
                                <strong><span data-market-metric="restock-volume">{{ $volume($marketReport['totals']['restock_volume']) }}</span> m&sup3;</strong>
                            </div>
                            <div class="market-seeding-metric">
                                <span>Missing</span>
                                <strong><span data-market-metric="missing">{{ $whole($marketReport['totals']['missing_lines']) }}</span> lines</strong>
                            </div>
                        </div>

                        <div class="table-responsive market-seeding-table-shell">
                            <table class="table table-sm table-hover market-seeding-dashboard-table" id="market-seeding-dashboard-table-{{ $market->id }}">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Category</th>
                                        <th>Group</th>
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
                                        <tr class="{{ $row['is_low'] ? 'table-warning' : '' }}"
                                            data-category="{{ $row['type_category'] }}"
                                            data-group="{{ $row['type_group'] ?? 'Unknown' }}"
                                            data-current-quantity="{{ $row['current_quantity'] }}"
                                            data-desired-quantity="{{ $row['item']->desired_quantity }}"
                                            data-covered-quantity="{{ min($row['current_quantity'], $row['item']->desired_quantity) }}"
                                            data-missing-quantity="{{ $row['missing_quantity'] }}"
                                            data-seeded-value="{{ $row['seeded_value'] }}"
                                            data-desired-value="{{ $row['desired_value'] }}"
                                            data-restock-cost="{{ $row['restock_cost'] }}"
                                            data-restock-volume="{{ $row['restock_volume'] }}">
                                            <td>
                                                @include('seat-market-seeding::partials.source-icons', ['sourceFlags' => $row['source_flags']])
                                                {{ $row['item']->type_name }}
                                                <button type="button"
                                                        class="btn btn-link btn-xs p-0 market-seeding-view-item"
                                                        title="View item details"
                                                        data-item-id="{{ $row['item']->id }}"
                                                        data-history-url="{{ route('market-seeding.items.history', $row['item']->id) }}"
                                                        data-item-name="{{ $row['item']->type_name }}"
                                                        data-market-name="{{ $market->name }} - {{ $market->location_name }}"
                                                        data-desired-quantity="{{ $row['item']->desired_quantity }}"
                                                        data-warning-quantity="{{ $row['item']->warning_quantity }}">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                                <span class="text-muted small market-seeding-item-type">{{ $row['type_category'] }} &middot; {{ $row['type_group'] ?? 'Unknown' }}</span>
                                            </td>
                                            <td>{{ $row['type_category'] }}</td>
                                            <td>{{ $row['type_group'] ?? 'Unknown' }}</td>
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
                                This list follows the dashboard category and group filters.
                                Estimated restock volume: <span class="market-seeding-export-volume" data-default-volume="{{ $marketReport['totals']['restock_volume'] }}">{{ $volume($marketReport['totals']['restock_volume']) }}</span> m&sup3;
                            </p>
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
    @include('seat-market-seeding::partials.item-detail-modal', [
        'marketSeedingThemeClass' => $marketSeedingThemeClass,
        'canManageMarketSeeding' => false,
    ])
    </div>
@endsection

@push('javascript')
    <script>
        $(function () {
            var dashboardTables = null;
            var targetTrendChart = null;
            var dashboardItemDetails = @json($dashboardItemDetails);

            if ($.fn.DataTable) {
                dashboardTables = $('.market-seeding-dashboard-table').DataTable({
                    order: [[0, 'asc']],
                    paging: true,
                    pageLength: 25,
                    lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
                    stateSave: true,
                    autoWidth: false,
                    columnDefs: [
                        { targets: [1, 2], visible: false }
                    ],
                    stateSaveParams: function (settings, data) {
                        data.marketSeedingSchema = 5;
                    },
                    stateLoadParams: function (settings, data) {
                        return data.marketSeedingSchema === 5;
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

            $('#market-seeding-market-filter').on('change', function () {
                var marketId = $(this).val();
                var cards = $('.market-seeding-card[data-market-id]');

                if (marketId === 'all') {
                    cards.show();
                    if (dashboardTables) {
                        dashboardTables.columns.adjust();
                    }
                    updateAllRestockExports();
                    return;
                }

                cards.hide();
                var selected = $('.market-seeding-card[data-market-id="' + marketId + '"]');
                selected.show();
                selected.find('.collapse').collapse('show');
                if (dashboardTables) {
                    dashboardTables.columns.adjust();
                }
                updateAllRestockExports();
            });

            $('#market-seeding-type-filter').on('change', function () {
                updateGroupFilterOptions();
                applyDashboardFilters();
            });

            $('#market-seeding-group-filter').on('change', function () {
                applyDashboardFilters();
            });

            $('#market-seeding-reset-filters').on('click', function () {
                $('#market-seeding-market-filter').val('all').trigger('change');
                $('#market-seeding-type-filter').val('');
                $('#market-seeding-group-filter').val('');
                updateGroupFilterOptions();
                applyDashboardFilters();
            });

            $('#market-seeding-filter-body').on('shown.bs.collapse', function () {
                updateFilterToggleButton(true);
            });

            $('#market-seeding-filter-body').on('hidden.bs.collapse', function () {
                updateFilterToggleButton(false);
            });

            $('.market-seeding-modal').on('show.bs.modal', function () {
                updateRestockExport($(this));
            });

            $(document).on('click', '.market-seeding-view-item', function () {
                openDashboardItemDetails(itemDetailsFromButton($(this)), true);
            });

            $('#market-seeding-edit-target-modal').on('hidden.bs.modal', function () {
                if (parseDashboardItemHash(window.location.hash)) {
                    replaceDashboardHash('');
                }
            });

            $(window).on('hashchange', function () {
                openDashboardItemFromHash();
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

            updateGroupFilterOptions();
            applyDashboardFilters();
            updateFilterToggleButton(false);
            openDashboardItemFromHash();

            function applyDashboardFilters() {
                var typeCategory = $('#market-seeding-type-filter').val();
                var typeGroup = $('#market-seeding-group-filter').val();

                $('.market-seeding-dashboard-table').each(function () {
                    if (!$.fn.DataTable || !$.fn.DataTable.isDataTable(this)) {
                        $(this).find('tbody tr').each(function () {
                            var matches = matchesTypeFilters($(this).data('category'), $(this).data('group'), typeCategory, typeGroup);
                            $(this).toggle(matches);
                        });
                        return;
                    }

                    var table = $(this).DataTable();
                    table
                        .column(1)
                        .search(typeCategory ? '^' + escapeRegex(typeCategory) + '$' : '', true, false);
                    table
                        .column(2)
                        .search(typeGroup ? '^' + escapeRegex(typeGroup) + '$' : '', true, false)
                        .draw();
                });

                updateMarketMetricCards();
                updateAllRestockExports();
            }

            function updateGroupFilterOptions() {
                var typeCategory = $('#market-seeding-type-filter').val();
                var groupFilter = $('#market-seeding-group-filter');
                var selectedGroup = groupFilter.val();
                var selectedStillVisible = !selectedGroup;

                groupFilter.find('option').each(function () {
                    if (!$(this).val()) {
                        $(this).show();
                        return;
                    }

                    var visible = !typeCategory || $(this).data('category') === typeCategory;
                    $(this).toggle(visible);

                    if (visible && $(this).val() === selectedGroup) {
                        selectedStillVisible = true;
                    }
                });

                if (!selectedStillVisible) {
                    groupFilter.val('');
                }
            }

            function updateAllRestockExports() {
                $('.market-seeding-modal').each(function () {
                    updateRestockExport($(this));
                });
            }

            function updateMarketMetricCards() {
                $('.market-seeding-card[data-market-id]').each(function () {
                    var $card = $(this);
                    var $table = $card.find('.market-seeding-dashboard-table');
                    var rows = filteredMarketRows($table);
                    var totals = {
                        desiredQuantity: 0,
                        coveredQuantity: 0,
                        seededValue: 0,
                        desiredValue: 0,
                        restockCost: 0,
                        restockVolume: 0,
                        missingLines: 0
                    };

                    rows.each(function () {
                        var $row = $(this);
                        var desiredQuantity = Number($row.data('desired-quantity') || 0);
                        var missingQuantity = Number($row.data('missing-quantity') || 0);

                        totals.desiredQuantity += desiredQuantity;
                        totals.coveredQuantity += Number($row.data('covered-quantity') || 0);
                        totals.seededValue += Number($row.data('seeded-value') || 0);
                        totals.desiredValue += Number($row.data('desired-value') || 0);
                        totals.restockCost += Number($row.data('restock-cost') || 0);
                        totals.restockVolume += Number($row.data('restock-volume') || 0);
                        totals.missingLines += missingQuantity > 0 ? 1 : 0;
                    });

                    var health = totals.desiredQuantity > 0
                        ? (totals.coveredQuantity / totals.desiredQuantity) * 100
                        : 100;

                    $card.find('[data-market-metric="health"], [data-market-metric="header-health"]').text(formatPercent(health));
                    $card.find('[data-market-metric="seeded"]').text(formatMetricMoney(totals.seededValue));
                    $card.find('[data-market-metric="target"]').text(formatMetricMoney(totals.desiredValue));
                    $card.find('[data-market-metric="restock"]').text(formatMetricMoney(totals.restockCost));
                    $card.find('[data-market-metric="restock-volume"], [data-market-metric="header-restock-volume"]').text(formatDecimal(totals.restockVolume));
                    $card.find('[data-market-metric="missing"], [data-market-metric="header-missing"]').text(numberWithCommas(totals.missingLines));
                    $card.find('[data-market-metric="header-restock"]').text(formatMetricMoney(totals.restockCost));
                    updateMarketHealthBadge($card.find('.market-seeding-health-badge'), health);
                });
            }

            function filteredMarketRows($table) {
                if ($table.length && $.fn.DataTable && $.fn.DataTable.isDataTable($table[0])) {
                    return $($table.DataTable().rows({ search: 'applied' }).nodes());
                }

                return $table.find('tbody tr:visible');
            }

            function updateMarketHealthBadge($badge, health) {
                $badge
                    .removeClass('badge-success badge-warning badge-danger')
                    .addClass(health >= 90 ? 'badge-success' : (health >= 60 ? 'badge-warning' : 'badge-danger'));
            }

            function updateRestockExport(modal) {
                var textarea = modal.find('.market-seeding-export-textarea')[0];

                if (!textarea) {
                    return;
                }

                var typeCategory = $('#market-seeding-type-filter').val();
                var typeGroup = $('#market-seeding-group-filter').val();
                var lines = $(textarea).data('lines') || [];
                var filtered = $.grep(lines, function (line) {
                    return matchesTypeFilters(line.category, line.group, typeCategory, typeGroup);
                });
                var volume = filtered.reduce(function (total, line) {
                    return total + Number(line.volume || 0);
                }, 0);

                textarea.value = $.map(filtered, function (line) {
                    return line.line;
                }).join('\n');

                modal.find('.market-seeding-export-volume').text(formatDecimal(volume));
            }

            function matchesTypeFilters(category, group, selectedCategory, selectedGroup) {
                return (!selectedCategory || category === selectedCategory)
                    && (!selectedGroup || group === selectedGroup);
            }

            function updateFilterToggleButton(expanded) {
                $('#market-seeding-toggle-filters')
                    .attr('aria-expanded', expanded ? 'true' : 'false')
                    .html(expanded
                        ? '<i class="fas fa-sliders-h"></i> Hide Filters'
                        : '<i class="fas fa-sliders-h"></i> Show Filters');
            }

            function escapeRegex(value) {
                return String(value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            }

            function formatDecimal(value, decimals) {
                decimals = typeof decimals === 'number' ? decimals : 2;

                return Number(value || 0).toLocaleString('en-US', {
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals
                });
            }

            function formatPercent(value) {
                return Number(value || 0).toLocaleString('en-US', {
                    minimumFractionDigits: 1,
                    maximumFractionDigits: 1
                }) + '%';
            }

            function formatMetricMoney(value) {
                return Number(value || 0).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }) + ' ISK';
            }

            function numberWithCommas(value) {
                value = parseInt(value || 0, 10);

                return value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            }

            function formatMoney(value) {
                value = parseFloat(value);

                if (!isFinite(value) || value <= 0) {
                    return '-';
                }

                return value.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }) + ' ISK';
            }

            function escapeHtml(value) {
                return $('<div>').text(value || '').html();
            }

            function eveTypeIconUrl(typeId, size) {
                typeId = parseInt(typeId || 0, 10);
                size = size || 64;

                return typeId > 0 ? 'https://images.evetech.net/types/' + typeId + '/icon?size=' + size : '';
            }

            function eveTypeRenderUrl(typeId, size) {
                typeId = parseInt(typeId || 0, 10);
                size = size || 64;

                return typeId > 0 ? 'https://images.evetech.net/types/' + typeId + '/render?size=' + size : '';
            }

            function resetItemDetails() {
                $('#market-seeding-detail-current').text('Loading...');
                $('#market-seeding-detail-missing').text('Loading...');
                $('#market-seeding-detail-hero-missing').text('Loading...');
                $('#market-seeding-detail-local-price').text('Loading...');
                $('#market-seeding-detail-price-delta').text('vs Jita');
                $('#market-seeding-detail-jita-price').text('Loading...');
                $('#market-seeding-detail-seeded-value').text('Loading...');
                $('#market-seeding-detail-target-value').text('Loading...');
                $('#market-seeding-detail-restock-value').text('Loading...');
                $('#market-seeding-detail-restock-volume').text('Loading...');
                $('#market-seeding-detail-item-volume').text('Packaged m3');
                $('#market-seeding-detail-source-badges').empty();
                $('#market-seeding-detail-source-list').html('<div class="text-muted">Loading source details...</div>');
                $('#market-seeding-detail-trend-summary').text('Loading...');
                $('#market-seeding-edit-target-history').html('<tr><td colspan="5" class="text-muted">Loading transition history...</td></tr>');
                $('#market-seeding-edit-target-change-history').html('<tr><td colspan="5" class="text-muted">Loading target changes...</td></tr>');
                $('#market-seeding-edit-target-icon').addClass('d-none').attr('src', '').attr('alt', '');
                $('.edit-target-delta').text('').removeClass('is-positive is-negative');

                if (targetTrendChart) {
                    targetTrendChart.destroy();
                    targetTrendChart = null;
                }
            }

            function loadItemDetails(url) {
                if (!url) {
                    $('#market-seeding-edit-target-error').removeClass('d-none').text('No item detail URL was provided.');
                    return;
                }

                $.getJSON(url)
                    .done(function (response) {
                        renderItemHeader(response.item || {});
                        renderItemDetails(response.details || {});
                        renderSourceDetails(response.source_details || {});
                        renderTrend(response.trend || {});
                        renderTransitionRows(response.events || []);
                        renderTargetChangeRows(response.target_history || []);
                    })
                    .fail(function () {
                        $('#market-seeding-edit-target-error').removeClass('d-none').text('Unable to load item details.');
                    });
            }

            function renderItemHeader(item) {
                var iconUrl = eveTypeIconUrl(item.type_id, 64);

                $('#market-seeding-edit-target-item').text(item.type_name || $('#market-seeding-edit-target-item').text());
                $('#market-seeding-edit-target-market').text(
                    item.market_name ? item.market_name + ' - ' + (item.location_name || '') : $('#market-seeding-edit-target-market').text()
                );

                if (!iconUrl) {
                    return;
                }

                $('#market-seeding-edit-target-icon')
                    .removeClass('d-none')
                    .attr('src', iconUrl)
                    .attr('alt', (item.type_name || 'Item') + ' icon');
            }

            function renderItemDetails(details) {
                var current = parseInt(details.current_quantity || 0, 10);
                var desired = parseInt(details.desired_quantity || 0, 10);
                var missing = Math.max(0, desired - current);

                $('#market-seeding-detail-current').text(numberWithCommas(current));
                $('#market-seeding-detail-missing').text(numberWithCommas(missing));
                $('#market-seeding-detail-hero-missing').text(numberWithCommas(missing));
                $('#market-seeding-detail-local-price').text(formatMoney(details.local_price || details.jita_price));
                $('#market-seeding-detail-jita-price').text(formatMoney(details.jita_price));
                $('#market-seeding-detail-seeded-value').text(formatMoney(details.seeded_value));
                $('#market-seeding-detail-target-value').text(formatMoney(details.desired_value));
                $('#market-seeding-detail-restock-value').text(formatMoney(details.restock_cost));
                $('#market-seeding-detail-restock-volume').text(formatDecimal(details.restock_volume, 2) + ' m3');
                $('#market-seeding-detail-item-volume').text(formatDecimal(details.item_volume, 2) + ' m3 each, packaged');

                if (details.price_delta === null || typeof details.price_delta === 'undefined') {
                    $('#market-seeding-detail-price-delta').text(details.jita_price ? 'No local market price' : 'No Jita comparison');
                } else {
                    var delta = parseFloat(details.price_delta);
                    $('#market-seeding-detail-price-delta').text((delta > 0 ? '+' : '') + formatDecimal(delta, 1) + '% vs Jita');
                }
            }

            function renderSourceDetails(sourceDetails) {
                var flags = sourceDetails.flags || {};
                var manualSources = sourceDetails.manual || [];
                var doctrines = sourceDetails.doctrines || [];
                var $badges = $('#market-seeding-detail-source-badges').empty();
                var $list = $('#market-seeding-detail-source-list').empty();

                if (flags.manual) {
                    $badges.append('<span class="badge badge-primary">Manual</span>');
                }

                if (flags.doctrine) {
                    $badges.append('<span class="badge badge-info">Doctrine</span>');
                }

                if (!flags.manual && !flags.doctrine) {
                    $badges.append('<span class="badge badge-secondary">Unknown</span>');
                    $list.html('<div class="text-muted">No source records were found for this item.</div>');
                    return;
                }

                $.each(manualSources, function (index, source) {
                    $list.append(
                        '<div class="edit-target-source-card">' +
                            '<div class="edit-target-source-name">' + escapeHtml(source.label || 'Manual add') + '</div>' +
                            '<div class="edit-target-source-meta">Target contribution ' + numberWithCommas(source.quantity) +
                                ', warning ' + numberWithCommas(source.warning_quantity || 0) + '</div>' +
                        '</div>'
                    );
                });

                $.each(doctrines, function (index, doctrine) {
                    var fitHtml = '';

                    $.each(doctrine.fits || [], function (fitIndex, fit) {
                        var shipIconUrl = eveTypeRenderUrl(fit.ship_type_id, 64) || eveTypeIconUrl(fit.ship_type_id, 64);
                        var shipIcon = shipIconUrl
                            ? '<img src="' + escapeHtml(shipIconUrl) + '" alt="' + escapeHtml((fit.ship_type_name || 'Ship') + ' image') + '" class="edit-target-ship-icon">'
                            : '';
                        var contributions = (fit.contributions || []).map(function (contribution) {
                            return '<span class="edit-target-source-contribution">' +
                                escapeHtml(contribution.kind || 'Item') + ': ' + numberWithCommas(contribution.quantity) +
                            '</span>';
                        }).join('');

                        fitHtml +=
                            '<div class="edit-target-source-fit">' +
                                shipIcon +
                                '<div class="edit-target-source-fit-body">' +
                                    '<div class="edit-target-source-fit-name">' + escapeHtml(fit.ship_type_name || 'Unknown Ship') + '</div>' +
                                    '<div class="edit-target-source-fit-meta">' + escapeHtml(fit.fitting_name || 'Unnamed Fit') +
                                        ' · ship x' + numberWithCommas(fit.ship_multiplier || 0) +
                                        ' · fit x' + numberWithCommas(fit.fitting_multiplier || 0) + '</div>' +
                                    '<div class="edit-target-source-fit-meta mt-1">' + contributions + '</div>' +
                                '</div>' +
                            '</div>';
                    });

                    if (!fitHtml) {
                        fitHtml = '<div class="edit-target-source-fit-meta mt-1">No matching fit breakdown could be loaded.</div>';
                    }

                    $list.append(
                        '<div class="edit-target-source-card">' +
                            '<div class="d-flex justify-content-between align-items-start">' +
                                '<div>' +
                                    '<div class="edit-target-source-name">' + escapeHtml(doctrine.name || 'Tracked doctrine') + '</div>' +
                                    '<div class="edit-target-source-meta">Doctrine contribution ' + numberWithCommas(doctrine.quantity) +
                                        ', warning ' + numberWithCommas(doctrine.warning_quantity || 0) +
                                        ' · merge ' + escapeHtml(doctrine.merge_mode || '-') +
                                        ' · fits ' + escapeHtml(doctrine.fit_aggregation_mode || '-') + '</div>' +
                                '</div>' +
                                '<span class="badge badge-info">Doctrine</span>' +
                            '</div>' +
                            fitHtml +
                        '</div>'
                    );
                });
            }

            function renderTrend(trend) {
                var labels = trend.labels || [];
                var values = trend.values || [];

                $('#market-seeding-detail-trend-summary').text(
                    numberWithCommas(trend.total || 0) + ' estimated sold over ' + numberWithCommas(trend.days || labels.length || 0) + ' days'
                );

                if (!window.Chart || !document.getElementById('market-seeding-detail-trend-chart')) {
                    return;
                }

                targetTrendChart = new Chart(document.getElementById('market-seeding-detail-trend-chart'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Estimated Sold',
                            data: values,
                            backgroundColor: 'rgba(23, 162, 184, .18)',
                            borderColor: 'rgba(23, 162, 184, .95)',
                            borderWidth: 2,
                            fill: true,
                            tension: .28
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true, ticks: { precision: 0 } }
                        }
                    }
                });
            }

            function renderTransitionRows(events) {
                var $body = $('#market-seeding-edit-target-history').empty();

                if (!events.length) {
                    $body.html('<tr><td colspan="5" class="text-muted">No stock transitions found for this item.</td></tr>');
                    return;
                }

                $.each(events, function (index, event) {
                    $body.append(
                        '<tr>' +
                            '<td>' + escapeHtml(event.created_at || '-') + '</td>' +
                            '<td>' + statusHtml(event.previous_status, event.current_status) + '</td>' +
                            '<td class="text-right">' + numberWithCommas(event.current_quantity) + '</td>' +
                            '<td class="text-right">' + numberWithCommas(event.warning_quantity) + '</td>' +
                            '<td class="text-right">' + numberWithCommas(event.desired_quantity) + '</td>' +
                        '</tr>'
                    );
                });
            }

            function statusHtml(previousStatus, currentStatus) {
                var badgeClass = {
                    stocked: 'badge-success',
                    low: 'badge-warning',
                    empty: 'badge-danger'
                }[currentStatus] || 'badge-secondary';

                return '<span class="badge ' + badgeClass + '">' + escapeHtml(capitalize(currentStatus || 'unknown')) + '</span>' +
                    (previousStatus ? ' <span class="text-muted small">' + escapeHtml(previousStatus) + ' &rarr; ' + escapeHtml(currentStatus) + '</span>' : '');
            }

            function capitalize(value) {
                value = String(value || '');

                return value.charAt(0).toUpperCase() + value.slice(1);
            }

            function renderTargetChangeRows(rows) {
                var $body = $('#market-seeding-edit-target-change-history').empty();

                if (!rows.length) {
                    $body.html('<tr><td colspan="5" class="text-muted">No target changes found for this item.</td></tr>');
                    return;
                }

                $.each(rows, function (index, row) {
                    $body.append(
                        '<tr>' +
                            '<td>' + escapeHtml(row.created_at || '-') + '</td>' +
                            '<td>' + escapeHtml(row.change_type_label || row.change_type || '-') + '</td>' +
                            '<td>' + escapeHtml(row.user_name || 'System') + '</td>' +
                            '<td class="text-right">' + numberWithCommas(row.old_target_quantity) + ' -> ' + numberWithCommas(row.new_target_quantity) + '</td>' +
                            '<td class="text-right">' + numberWithCommas(row.old_warning_quantity) + ' -> ' + numberWithCommas(row.new_warning_quantity) + '</td>' +
                        '</tr>'
                    );
                });
            }

            function openDashboardItemDetails(itemDetails, updateHash) {
                if (!itemDetails || !itemDetails.history_url) {
                    return false;
                }

                var itemId = itemDetails.item_id;
                var $card = $('.market-seeding-card[data-market-id="' + itemDetails.market_id + '"]');

                if ($card.length) {
                    $card.show();
                    $card.find('.collapse').collapse('show');
                }

                $('#market-seeding-edit-target-title').text('Item Details');
                $('#market-seeding-edit-target-modal').addClass('is-read-only');
                $('#market-seeding-edit-target-adjust-panel').hide();
                $('#market-seeding-edit-target-save').hide();
                $('#market-seeding-edit-target-form').attr('action', '');
                $('#market-seeding-edit-target-item').text(itemDetails.item_name);
                $('#market-seeding-edit-target-market').text(itemDetails.market_name);
                $('#market-seeding-edit-target-quantity').val(itemDetails.desired_quantity);
                $('#market-seeding-edit-warning-quantity').val(itemDetails.warning_quantity);
                $('#market-seeding-edit-target-success').addClass('d-none').text('');
                $('#market-seeding-edit-target-error').addClass('d-none').text('');

                if (updateHash && itemId) {
                    replaceDashboardHash('#item-' + itemId);
                }

                resetItemDetails();
                loadItemDetails(itemDetails.history_url);
                $('#market-seeding-edit-target-modal').modal('show');

                return true;
            }

            function itemDetailsFromButton($button) {
                return {
                    item_id: $button.data('item-id'),
                    market_id: $button.closest('.market-seeding-card').data('market-id'),
                    history_url: $button.data('history-url'),
                    item_name: $button.data('item-name'),
                    market_name: $button.data('market-name'),
                    desired_quantity: $button.data('desired-quantity'),
                    warning_quantity: $button.data('warning-quantity')
                };
            }

            function openDashboardItemFromHash() {
                var itemId = parseDashboardItemHash(window.location.hash);

                if (!itemId) {
                    return false;
                }

                return openDashboardItemDetails(dashboardItemDetails[itemId], false);
            }

            function parseDashboardItemHash(hash) {
                var match = String(hash || '').match(/^#item-(\d+)$/);

                return match ? match[1] : null;
            }

            function replaceDashboardHash(hash) {
                var url = window.location.pathname + window.location.search + (hash || '');

                if (window.history && window.history.replaceState) {
                    window.history.replaceState(null, document.title, url);
                    return;
                }

                if (hash) {
                    window.location.hash = hash;
                }
            }
        });
    </script>
@endpush
