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
        $healthTooltip = 'Health is based on tracked item lines. Stocked items have no penalty, low items count as half unhealthy, and empty items count as fully unhealthy.';
        $priorityHelpTooltip = 'Priority helps sort restock work. It combines stock status, percent of target missing, recent estimated sales, and restock value. Empty items score higher than low items, and expensive or frequently sold items can move up the list.';
        $priorityBadge = function ($level) {
            return [
                'critical' => 'badge-danger',
                'high' => 'badge-warning',
                'medium' => 'badge-info',
                'low' => 'badge-secondary',
                'none' => 'badge-success',
            ][$level] ?? 'badge-secondary';
        };
        $priorityTooltip = function ($priority) use ($whole, $percent, $isk) {
            return implode('<br>', [
                '<strong>' . e($priority['label']) . ' priority: ' . $priority['score'] . '</strong>',
                'Status: +' . $priority['status_score'],
                'Missing: ' . $percent($priority['missing_percent']) . ' = +' . $priority['coverage_score'],
                'Sales: ' . $whole($priority['estimated_sold_quantity']) . ' / ' . $whole($priority['sales_window_days']) . ' days = +' . $priority['sales_score'],
                'Value: ' . $isk($priority['restock_cost']) . ' = +' . $priority['value_score'],
                '<strong>Total: ' . $priority['status_score'] . ' + ' . $priority['coverage_score'] . ' + ' . $priority['sales_score'] . ' + ' . $priority['value_score'] . ' = ' . $priority['score'] . '</strong>',
            ]);
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
        .market-seeding-listing-helper-grid {
            display: grid;
            align-items: start;
            gap: 1rem;
            grid-template-columns: minmax(0, 1.2fr) minmax(320px, .8fr);
        }
        .market-seeding-listing-helper-intro {
            align-items: flex-start;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            display: flex;
            gap: .75rem;
            margin-bottom: 1rem;
            padding: .8rem .95rem;
        }
        .market-seeding-listing-helper-intro i {
            color: #17a2b8;
            margin-top: .15rem;
        }
        .market-seeding-listing-helper-panel {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: .9rem;
        }
        .market-seeding-listing-helper-panel + .market-seeding-listing-helper-panel {
            margin-top: 1rem;
        }
        .market-seeding-listing-helper-section-title {
            align-items: center;
            display: flex;
            gap: .45rem;
            font-size: .82rem;
            font-weight: 800;
            letter-spacing: .04em;
            margin-bottom: .8rem;
            text-transform: uppercase;
        }
        .market-seeding-listing-helper-section-title i {
            color: #17a2b8;
        }
        .market-seeding-listing-helper-settings {
            display: grid;
            gap: .65rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .market-seeding-listing-helper-settings .form-group {
            margin-bottom: 0;
        }
        .market-seeding-listing-helper-option {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: .7rem .75rem .7rem 2.35rem;
        }
        .market-seeding-listing-helper-option label {
            font-size: .86rem;
            line-height: 1.35;
            margin-bottom: 0;
        }
        .market-seeding-listing-helper-option small {
            display: block;
            line-height: 1.3;
            margin-top: .25rem;
        }
        .market-seeding-listing-helper-stat-grid {
            display: grid;
            gap: .6rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            margin-bottom: 1rem;
        }
        .market-seeding-listing-helper-stat {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: .6rem .7rem;
        }
        .market-seeding-listing-helper-stat span {
            color: #6c757d;
            display: block;
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .market-seeding-listing-helper-stat strong {
            display: block;
            font-size: 1rem;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }
        .market-seeding-listing-helper-stat.is-wide {
            grid-column: span 3;
        }
        .market-seeding-listing-helper-output-header {
            align-items: center;
            display: flex;
            justify-content: space-between;
            margin-bottom: .45rem;
        }
        .market-seeding-listing-helper-output {
            font-family: Menlo, Monaco, Consolas, "Liberation Mono", monospace;
        }
        .market-seeding-listing-helper-warning {
            font-size: .82rem;
            margin-top: 1rem;
            max-height: 160px;
            overflow-y: auto;
        }
        .market-seeding-listing-helper-review {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-top: 1rem;
            padding: .9rem;
        }
        .market-seeding-listing-helper-review-table {
            margin-bottom: 0;
        }
        .market-seeding-listing-helper-review-table th {
            border-top: 0;
            color: #6c757d;
            font-size: .72rem;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .market-seeding-listing-helper-review-table td {
            vertical-align: middle;
        }
        .market-seeding-listing-helper-review-table .badge {
            margin: .05rem .1rem .05rem 0;
        }
        @media (max-width: 991px) {
            .market-seeding-listing-helper-grid,
            .market-seeding-listing-helper-settings,
            .market-seeding-listing-helper-stat-grid {
                grid-template-columns: 1fr;
            }
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
        .market-seeding-modal.market-seeding-dark-skin .market-seeding-listing-helper-panel input.form-control {
            background: #2f3a40;
            border-color: #4c5a61;
            color: #e9ecef;
        }
        .market-seeding-modal.market-seeding-dark-skin .market-seeding-listing-helper-intro,
        .market-seeding-modal.market-seeding-dark-skin .market-seeding-listing-helper-panel,
        .market-seeding-modal.market-seeding-dark-skin .market-seeding-listing-helper-option,
        .market-seeding-modal.market-seeding-dark-skin .market-seeding-listing-helper-review {
            background: #1f292e;
            border-color: #3c4b54;
        }
        .market-seeding-modal.market-seeding-dark-skin .market-seeding-listing-helper-section-title i,
        .market-seeding-modal.market-seeding-dark-skin .market-seeding-listing-helper-intro i {
            color: #55c3c7;
        }
        .market-seeding-modal.market-seeding-dark-skin .market-seeding-listing-helper-stat {
            background: #1f292e;
            border-color: #3c4b54;
        }
        .market-seeding-modal.market-seeding-dark-skin .market-seeding-listing-helper-stat span {
            color: #b8c7ce;
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
                        <div class="market-seeding-filter-field">
                            <label for="market-seeding-stock-status-filter">Stock Status</label>
                            <select class="form-control form-control-sm" id="market-seeding-stock-status-filter">
                                <option value="">All Statuses</option>
                                <option value="low_or_empty">Low Warning + Empty</option>
                                <option value="low">Low Warning</option>
                                <option value="empty">Empty</option>
                            </select>
                        </div>
                        <div class="market-seeding-filter-field">
                            <label for="market-seeding-priority-filter">
                                Priority
                                <i class="fas fa-question-circle text-muted"
                                   data-toggle="tooltip"
                                   title="{{ $priorityHelpTooltip }}"></i>
                            </label>
                            <select class="form-control form-control-sm" id="market-seeding-priority-filter">
                                <option value="">All Priorities</option>
                                <option value="critical">Critical</option>
                                <option value="high">High</option>
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                                <option value="none">None</option>
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
                            'status' => $row['stock_status'],
                            'priority' => $row['priority']['level'],
                            'name' => $row['item']->type_name,
                            'quantity' => $row['missing_quantity'],
                            'line' => $row['export_line'],
                            'volume' => $row['restock_volume'],
                            'unit_volume' => $row['missing_quantity'] > 0
                                ? $row['restock_volume'] / $row['missing_quantity']
                                : 0,
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
                            <span class="badge {{ $healthBadge }} market-seeding-health-badge" data-toggle="tooltip" title="{{ $healthTooltip }}">Health <span data-market-metric="header-health">{{ $percent($healthScore) }}</span></span>
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
                        <button type="button" class="btn btn-sm btn-default" data-toggle="modal" data-target="#{{ $exportId }}-listing-helper-modal">
                            <i class="fas fa-tags"></i> Listing Helper
                        </button>
                    </div>
                </div>
                <div id="{{ $collapseId }}" class="collapse {{ $startsExpanded ? 'show' : '' }}">
                    <div class="card-body">
                        <div class="market-seeding-metrics">
                            <div class="market-seeding-metric">
                                <span data-toggle="tooltip" title="{{ $healthTooltip }}">Health <i class="fas fa-question-circle text-muted"></i></span>
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
                                        <th>Status</th>
                                        <th>Priority Level</th>
                                        <th>Priority</th>
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
                                            data-stock-status="{{ $row['stock_status'] }}"
                                            data-priority="{{ $row['priority']['level'] }}"
                                            data-desired-quantity="{{ $row['item']->desired_quantity }}"
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
                                            <td>{{ $row['stock_status'] }}</td>
                                            <td>{{ $row['priority']['level'] }}</td>
                                            <td data-order="{{ $row['priority']['score'] }}">
                                                <span class="badge {{ $priorityBadge($row['priority']['level']) }}"
                                                      data-toggle="tooltip"
                                                      data-html="true"
                                                      title="{!! $priorityTooltip($row['priority']) !!}">
                                                    {{ $row['priority']['label'] }}
                                                </span>
                                            </td>
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
                                Estimated restock volume: <span class="market-seeding-export-volume">{{ $volume($marketReport['totals']['restock_volume']) }}</span> m&sup3;
                            </p>
                            <div class="form-group">
                                <label for="{{ $exportId }}-freight-limit">Remaining Freight Space</label>
                                <div class="input-group">
                                    <input type="number" class="form-control market-seeding-freight-limit" id="{{ $exportId }}-freight-limit" min="0" step="0.01" placeholder="Optional, e.g. 40000">
                                    <div class="input-group-append">
                                        <span class="input-group-text">m&sup3;</span>
                                    </div>
                                </div>
                                <small class="form-text text-muted">When set, the list is trimmed to fit as close as possible inside this remaining cargo space.</small>
                                <small class="form-text text-muted market-seeding-freight-result d-none"></small>
                            </div>
                            <textarea id="{{ $exportId }}" class="form-control market-seeding-export-textarea" rows="10" readonly data-lines='@json($restockLines)'>{{ $marketReport['export'] }}</textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary copy-market-export" data-target="{{ $exportId }}">
                                <i class="fas fa-copy"></i> Copy Multi-Buy
                            </button>
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade market-seeding-modal {{ $marketSeedingThemeClass }} market-seeding-listing-helper-modal" id="{{ $exportId }}-listing-helper-modal" tabindex="-1" role="dialog" aria-labelledby="{{ $exportId }}-listing-helper-label" aria-hidden="true" data-pricing-url="{{ route('market-seeding.markets.listing-helper.prices', $market->id) }}">
                <div class="modal-dialog modal-xl" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="{{ $exportId }}-listing-helper-label">{{ $market->name }} Listing Helper</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <div class="market-seeding-listing-helper-intro">
                                <i class="fas fa-magic"></i>
                                <div>
                                    <strong>Build EVE multi-sell lines from wallet transactions.</strong>
                                    <div class="text-muted small">
                                        Paste a character or corporation market transaction log. Duplicate items are grouped, the highest unit cost is used, and output is generated as <code>Item Name price</code>.
                                    </div>
                                </div>
                            </div>
                            <div class="market-seeding-listing-helper-grid">
                                <div>
                                    <div class="market-seeding-listing-helper-panel">
                                        <div class="market-seeding-listing-helper-section-title">
                                            <i class="fas fa-paste"></i> Paste Market Transactions
                                        </div>
                                        <div class="form-group mb-0">
                                            <textarea class="form-control market-seeding-listing-helper-input" rows="10" placeholder="Paste wallet transactions here..."></textarea>
                                            <small class="form-text text-muted">Supports character logs with 7 columns and corporation logs with 9 columns. The helper reads date, quantity, item name, unit cost, total cost, seller, and station.</small>
                                        </div>
                                    </div>
                                    <div class="market-seeding-listing-helper-panel">
                                        <div class="market-seeding-listing-helper-section-title">
                                            <i class="fas fa-sliders-h"></i> Pricing Rules
                                        </div>
                                        <div class="market-seeding-listing-helper-settings">
                                            <div class="form-group">
                                                <label>% Markup</label>
                                                <input type="number" class="form-control market-seeding-listing-helper-markup" value="25" step="0.01">
                                            </div>
                                            <div class="form-group">
                                                <label>Sales Tax %</label>
                                                <input type="number" class="form-control market-seeding-listing-helper-tax" value="3.37" step="0.01" min="0">
                                            </div>
                                            <div class="form-group">
                                                <label>Broker Fee %</label>
                                                <input type="number" class="form-control market-seeding-listing-helper-broker" value="1.00" step="0.01" min="0">
                                            </div>
                                            <div class="custom-control custom-checkbox market-seeding-listing-helper-option">
                                                <input type="checkbox" class="custom-control-input market-seeding-listing-helper-competitive" id="{{ $exportId }}-listing-helper-competitive">
                                                <label class="custom-control-label" for="{{ $exportId }}-listing-helper-competitive">
                                                    List competitively as lowest sell order
                                                    <small class="text-muted">When SeAT has local sell orders cached, use the lower of markup price and local undercut.</small>
                                                </label>
                                            </div>
                                            <div class="custom-control custom-checkbox market-seeding-listing-helper-option">
                                                <input type="checkbox" class="custom-control-input market-seeding-listing-helper-exclude-problem-items" id="{{ $exportId }}-listing-helper-exclude-problem-items">
                                                <label class="custom-control-label" for="{{ $exportId }}-listing-helper-exclude-problem-items">
                                                    Clean multi-sell output
                                                    <small class="text-muted">Remove SDE-missing and below break-even items from the copy box.</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="alert alert-warning d-none market-seeding-listing-helper-warning"></div>
                                </div>
                                <div>
                                    <div class="market-seeding-listing-helper-panel">
                                        <div class="market-seeding-listing-helper-section-title">
                                            <i class="fas fa-chart-pie"></i> Summary
                                        </div>
                                        <div class="market-seeding-listing-helper-stat-grid">
                                            <div class="market-seeding-listing-helper-stat">
                                                <span>Unique Items</span>
                                                <strong data-listing-helper-stat="items">0</strong>
                                            </div>
                                            <div class="market-seeding-listing-helper-stat">
                                                <span>Total Qty</span>
                                                <strong data-listing-helper-stat="quantity">0</strong>
                                            </div>
                                            <div class="market-seeding-listing-helper-stat">
                                                <span>Competitive</span>
                                                <strong data-listing-helper-stat="competitive">0</strong>
                                            </div>
                                            <div class="market-seeding-listing-helper-stat is-wide">
                                                <span>Sell Value</span>
                                                <strong data-listing-helper-stat="value">0.00 ISK</strong>
                                            </div>
                                            <div class="market-seeding-listing-helper-stat is-wide">
                                                <span>Profit</span>
                                                <strong data-listing-helper-stat="profit">0.00 ISK</strong>
                                            </div>
                                            <div class="market-seeding-listing-helper-stat is-wide">
                                                <span>Fees</span>
                                                <strong data-listing-helper-stat="fees">0.00 ISK</strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="market-seeding-listing-helper-panel">
                                        <div class="market-seeding-listing-helper-output-header">
                                            <div class="market-seeding-listing-helper-section-title mb-0">
                                                <i class="fas fa-tags"></i> Multi-Sell Output
                                            </div>
                                            <button type="button" class="btn btn-primary btn-sm market-seeding-copy-listing-helper">
                                                <i class="fas fa-copy"></i> Copy
                                            </button>
                                        </div>
                                        <textarea class="form-control market-seeding-listing-helper-output" rows="9" readonly></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="market-seeding-listing-helper-review d-none">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <strong>Item Review</strong>
                                    <span class="text-muted small">Populates automatically from the pasted transactions.</span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm market-seeding-listing-helper-review-table">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th class="text-right">Qty</th>
                                                <th class="text-right">Unit Cost</th>
                                                <th class="text-right">Unit Sell</th>
                                                <th class="text-right">Local Unit Sell</th>
                                                <th class="text-right">Total Profit</th>
                                                <th class="text-right">Profit %</th>
                                                <th>Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
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
            var listingHelperCsrfToken = '{{ csrf_token() }}';
            var listingHelperPreferenceKey = 'seat-market-seeding.listing-helper.preferences.v1';

            if ($.fn.DataTable) {
                dashboardTables = $('.market-seeding-dashboard-table').DataTable({
                    order: [[0, 'asc']],
                    paging: true,
                    pageLength: 25,
                    lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
                    stateSave: true,
                    autoWidth: false,
                    columnDefs: [
                        { targets: [1, 2, 3, 4], visible: false }
                    ],
                    stateSaveParams: function (settings, data) {
                        data.marketSeedingSchema = 8;
                    },
                    stateLoadParams: function (settings, data) {
                        return data.marketSeedingSchema === 8;
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

            $('.market-seeding-listing-helper-modal').on('shown.bs.modal', function () {
                applyListingHelperPreferences($(this));
                scheduleListingHelperUpdate($(this), 0);
            });

            $('.market-seeding-listing-helper-modal').on('hidden.bs.modal', function () {
                resetListingHelper($(this));
            });

            $(document).on('input change', '.market-seeding-listing-helper-input, .market-seeding-listing-helper-markup, .market-seeding-listing-helper-tax, .market-seeding-listing-helper-broker, .market-seeding-listing-helper-competitive, .market-seeding-listing-helper-exclude-problem-items', function () {
                var $modal = $(this).closest('.market-seeding-listing-helper-modal');

                if (!$(this).hasClass('market-seeding-listing-helper-input')) {
                    saveListingHelperPreferences($modal);
                }

                scheduleListingHelperUpdate($modal, 250);
            });

            $(document).on('click', '.market-seeding-copy-listing-helper', function () {
                var textarea = $(this).closest('.market-seeding-listing-helper-modal').find('.market-seeding-listing-helper-output')[0];

                textarea.select();
                document.execCommand('copy');
            });

            $(document).on('input change', '.market-seeding-freight-limit', function () {
                updateRestockExport($(this).closest('.market-seeding-modal'));
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

            $('#market-seeding-stock-status-filter').on('change', function () {
                applyDashboardFilters();
            });

            $('#market-seeding-priority-filter').on('change', function () {
                applyDashboardFilters();
            });

            $('#market-seeding-reset-filters').on('click', function () {
                $('#market-seeding-market-filter').val('all').trigger('change');
                $('#market-seeding-type-filter').val('');
                $('#market-seeding-group-filter').val('');
                $('#market-seeding-stock-status-filter').val('');
                $('#market-seeding-priority-filter').val('');
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
            if ($.fn.tooltip) {
                $('[data-toggle="tooltip"]').tooltip({
                    container: 'body',
                    html: true
                });
            }
            openDashboardItemFromHash();

            function applyDashboardFilters() {
                var typeCategory = $('#market-seeding-type-filter').val();
                var typeGroup = $('#market-seeding-group-filter').val();
                var stockStatus = $('#market-seeding-stock-status-filter').val();
                var priority = $('#market-seeding-priority-filter').val();

                $('.market-seeding-dashboard-table').each(function () {
                    if (!$.fn.DataTable || !$.fn.DataTable.isDataTable(this)) {
                        $(this).find('tbody tr').each(function () {
                            var matches = matchesDashboardFilters(
                                $(this).data('category'),
                                $(this).data('group'),
                                $(this).data('stock-status'),
                                $(this).data('priority'),
                                typeCategory,
                                typeGroup,
                                stockStatus,
                                priority
                            );
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
                        .search(typeGroup ? '^' + escapeRegex(typeGroup) + '$' : '', true, false);
                    table
                        .column(3)
                        .search(stockStatusSearchRegex(stockStatus), true, false);
                    table
                        .column(4)
                        .search(priority ? '^' + escapeRegex(priority) + '$' : '', true, false)
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
                        trackedLines: 0,
                        lowLines: 0,
                        emptyLines: 0,
                        seededValue: 0,
                        desiredValue: 0,
                        restockCost: 0,
                        restockVolume: 0,
                        missingLines: 0
                    };

                    rows.each(function () {
                        var $row = $(this);
                        var missingQuantity = Number($row.data('missing-quantity') || 0);
                        var stockStatus = String($row.data('stock-status') || '');

                        totals.trackedLines++;
                        totals.lowLines += stockStatus === 'low' ? 1 : 0;
                        totals.emptyLines += stockStatus === 'empty' ? 1 : 0;
                        totals.seededValue += Number($row.data('seeded-value') || 0);
                        totals.desiredValue += Number($row.data('desired-value') || 0);
                        totals.restockCost += Number($row.data('restock-cost') || 0);
                        totals.restockVolume += Number($row.data('restock-volume') || 0);
                        totals.missingLines += missingQuantity > 0 ? 1 : 0;
                    });

                    var health = healthScoreFromLines(totals.lowLines, totals.emptyLines, totals.trackedLines);

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

            function healthScoreFromLines(lowLines, emptyLines, trackedLines) {
                if (!trackedLines) {
                    return 100;
                }

                var penalty = (((lowLines * 0.5) + emptyLines) / trackedLines) * 100;

                return Math.max(0, Math.min(100, 100 - penalty));
            }

            function updateRestockExport(modal) {
                var textarea = modal.find('.market-seeding-export-textarea')[0];

                if (!textarea) {
                    return;
                }

                var typeCategory = $('#market-seeding-type-filter').val();
                var typeGroup = $('#market-seeding-group-filter').val();
                var stockStatus = $('#market-seeding-stock-status-filter').val();
                var priority = $('#market-seeding-priority-filter').val();
                var lines = $(textarea).data('lines') || [];
                var filtered = $.grep(lines, function (line) {
                    return matchesDashboardFilters(line.category, line.group, line.status, line.priority, typeCategory, typeGroup, stockStatus, priority);
                });
                var freightLimit = parsePositiveDecimal(modal.find('.market-seeding-freight-limit').val());
                var selected = applyFreightLimit(filtered, freightLimit);
                var volume = selected.reduce(function (total, line) {
                    return total + Number(line.volume || 0);
                }, 0);

                textarea.value = $.map(selected, function (line) {
                    return line.line;
                }).join('\n');

                modal.find('.market-seeding-export-volume').text(formatDecimal(volume));
                updateFreightResult(modal, freightLimit, volume);
            }

            function updateFreightResult(modal, freightLimit, selectedVolume) {
                var result = modal.find('.market-seeding-freight-result');

                if (!freightLimit || freightLimit <= 0) {
                    result.addClass('d-none').empty();
                    return;
                }

                result
                    .removeClass('d-none')
                    .html(
                        'Filtered list volume: <strong>' + formatDecimal(selectedVolume) + '</strong> m&sup3; of ' +
                        '<strong>' + formatDecimal(freightLimit) + '</strong> m&sup3; available. ' +
                        'Remaining: <strong>' + formatDecimal(Math.max(0, freightLimit - selectedVolume)) + '</strong> m&sup3;.'
                    );
            }

            function applyFreightLimit(lines, freightLimit) {
                if (!freightLimit || freightLimit <= 0) {
                    return lines;
                }

                var candidates = $.map(lines, function (line, index) {
                    return $.extend({}, line, {
                        originalIndex: index,
                        quantity: Number(line.quantity || 0),
                        unit_volume: Number(line.unit_volume || 0),
                        volume: Number(line.volume || 0)
                    });
                });
                var zeroVolumeSelections = {};

                $.each(candidates, function (index, line) {
                    if (line.unit_volume <= 0 && line.quantity > 0) {
                        zeroVolumeSelections[line.originalIndex] = $.extend({}, line);
                    }
                });

                var volumeCandidates = $.grep(candidates, function (line) {
                    return line.quantity > 0 && line.unit_volume > 0;
                });
                var packingOrders = [
                    volumeCandidates.slice().sort(function (a, b) {
                        return a.originalIndex - b.originalIndex;
                    }),
                    volumeCandidates.slice().sort(function (a, b) {
                        return a.unit_volume - b.unit_volume || a.originalIndex - b.originalIndex;
                    }),
                    volumeCandidates.slice().sort(function (a, b) {
                        if (b.unit_volume !== a.unit_volume) {
                            return b.unit_volume - a.unit_volume;
                        }

                        return a.originalIndex - b.originalIndex;
                    }),
                    volumeCandidates.slice().sort(function (a, b) {
                        if (b.volume !== a.volume) {
                            return b.volume - a.volume;
                        }

                        return a.originalIndex - b.originalIndex;
                    })
                ];
                var bestSelection = {
                    selectedByIndex: $.extend({}, zeroVolumeSelections),
                    volume: 0
                };

                $.each(packingOrders, function (index, orderedCandidates) {
                    var packed = packFreightCandidates(orderedCandidates, zeroVolumeSelections, freightLimit);

                    if (packed.volume > bestSelection.volume) {
                        bestSelection = packed;
                    }
                });

                return $.map(lines, function (line, index) {
                    return bestSelection.selectedByIndex[index] || null;
                });
            }

            function packFreightCandidates(candidates, zeroVolumeSelections, freightLimit) {
                var selectedByIndex = $.extend({}, zeroVolumeSelections);
                var remaining = freightLimit;
                var selectedVolume = 0;

                $.each(candidates, function (index, line) {
                    if (remaining <= 0) {
                        return false;
                    }

                    var quantity = Math.min(line.quantity, Math.floor((remaining + 0.0000001) / line.unit_volume));

                    if (quantity <= 0) {
                        return;
                    }

                    var lineVolume = quantity * line.unit_volume;
                    remaining -= lineVolume;
                    selectedVolume += lineVolume;
                    selectedByIndex[line.originalIndex] = $.extend({}, line, {
                        quantity: quantity,
                        volume: lineVolume,
                        line: line.name + '\t' + quantity
                    });
                });

                return {
                    selectedByIndex: selectedByIndex,
                    volume: selectedVolume
                };
            }

            function matchesDashboardFilters(category, group, stockStatus, priority, selectedCategory, selectedGroup, selectedStatus, selectedPriority) {
                return (!selectedCategory || category === selectedCategory)
                    && (!selectedGroup || group === selectedGroup)
                    && matchesStockStatusFilter(stockStatus, selectedStatus)
                    && (!selectedPriority || priority === selectedPriority);
            }

            function matchesStockStatusFilter(stockStatus, selectedStatus) {
                if (!selectedStatus) {
                    return true;
                }

                if (selectedStatus === 'low_or_empty') {
                    return stockStatus === 'low' || stockStatus === 'empty';
                }

                return stockStatus === selectedStatus;
            }

            function stockStatusSearchRegex(selectedStatus) {
                if (!selectedStatus) {
                    return '';
                }

                if (selectedStatus === 'low_or_empty') {
                    return '^(low|empty)$';
                }

                return '^' + escapeRegex(selectedStatus) + '$';
            }

            function updateFilterToggleButton(expanded) {
                $('#market-seeding-toggle-filters')
                    .attr('aria-expanded', expanded ? 'true' : 'false')
                    .html(expanded
                        ? '<i class="fas fa-sliders-h"></i> Hide Filters'
                        : '<i class="fas fa-sliders-h"></i> Show Filters');
            }

            function scheduleListingHelperUpdate($modal, delay) {
                var timer = $modal.data('listing-helper-timer');

                if (timer) {
                    window.clearTimeout(timer);
                }

                $modal.data('listing-helper-timer', window.setTimeout(function () {
                    updateListingHelper($modal);
                }, delay));
            }

            function resetListingHelper($modal) {
                var $table = $modal.find('.market-seeding-listing-helper-review-table');
                var timer = $modal.data('listing-helper-timer');

                if ($.fn.DataTable && $.fn.DataTable.isDataTable($table[0])) {
                    $table.DataTable().destroy();
                }

                if (timer) {
                    window.clearTimeout(timer);
                }

                $modal.find('.market-seeding-listing-helper-input').val('');
                $modal.find('.market-seeding-listing-helper-output').val('');
                $modal.find('.market-seeding-listing-helper-warning').addClass('d-none').empty();
                $modal.find('.market-seeding-listing-helper-review').addClass('d-none');
                $table.find('tbody').empty();
                $modal.removeData('listing-helper-timer listing-helper-price-key listing-helper-prices listing-helper-extra-warnings');

                $modal.find('[data-listing-helper-stat="items"]').text('0');
                $modal.find('[data-listing-helper-stat="quantity"]').text('0');
                $modal.find('[data-listing-helper-stat="value"]').text('0.00 ISK');
                $modal.find('[data-listing-helper-stat="profit"]').text('0.00 ISK');
                $modal.find('[data-listing-helper-stat="fees"]').text('0.00 ISK');
                $modal.find('[data-listing-helper-stat="competitive"]').text('0');
            }

            function applyListingHelperPreferences($modal) {
                var preferences = readListingHelperPreferences();

                if (!preferences) {
                    return;
                }

                if (preferences.markup !== undefined) {
                    $modal.find('.market-seeding-listing-helper-markup').val(preferences.markup);
                }

                if (preferences.tax !== undefined) {
                    $modal.find('.market-seeding-listing-helper-tax').val(preferences.tax);
                }

                if (preferences.broker !== undefined) {
                    $modal.find('.market-seeding-listing-helper-broker').val(preferences.broker);
                }

                if (preferences.competitive !== undefined) {
                    $modal.find('.market-seeding-listing-helper-competitive').prop('checked', !!preferences.competitive);
                }

                if (preferences.excludeProblemItems !== undefined) {
                    $modal.find('.market-seeding-listing-helper-exclude-problem-items').prop('checked', !!preferences.excludeProblemItems);
                }
            }

            function saveListingHelperPreferences($modal) {
                var storage = listingHelperStorage();

                if (!storage) {
                    return;
                }

                var preferences = {
                    markup: $modal.find('.market-seeding-listing-helper-markup').val(),
                    tax: $modal.find('.market-seeding-listing-helper-tax').val(),
                    broker: $modal.find('.market-seeding-listing-helper-broker').val(),
                    competitive: $modal.find('.market-seeding-listing-helper-competitive').is(':checked'),
                    excludeProblemItems: $modal.find('.market-seeding-listing-helper-exclude-problem-items').is(':checked')
                };

                try {
                    storage.setItem(listingHelperPreferenceKey, JSON.stringify(preferences));
                } catch (e) {
                    // Some browsers block localStorage in private modes. The helper still works without saved preferences.
                }
            }

            function readListingHelperPreferences() {
                var storage = listingHelperStorage();

                if (!storage) {
                    return null;
                }

                try {
                    return JSON.parse(storage.getItem(listingHelperPreferenceKey) || 'null');
                } catch (e) {
                    return null;
                }
            }

            function listingHelperStorage() {
                try {
                    return window.localStorage || null;
                } catch (e) {
                    return null;
                }
            }

            function updateListingHelper($modal) {
                var parsed = parseListingHelperTransactions($modal.find('.market-seeding-listing-helper-input').val());
                var names = Object.keys(parsed.items).sort();
                var priceKey = names.join('\n');

                if (!names.length) {
                    renderListingHelper($modal, parsed, {});
                    return;
                }

                if ($modal.data('listing-helper-price-key') !== priceKey) {
                    $modal.data('listing-helper-price-key', priceKey);
                    $modal.data('listing-helper-prices', {});
                    fetchListingHelperPrices($modal, names, priceKey);
                }

                renderListingHelper($modal, parsed, $modal.data('listing-helper-prices') || {});
            }

            function fetchListingHelperPrices($modal, names, priceKey) {
                $.ajax({
                    url: $modal.data('pricing-url'),
                    method: 'POST',
                    data: {
                        _token: listingHelperCsrfToken,
                        items: names
                    },
                    headers: {
                        'Accept': 'application/json'
                    }
                }).done(function (response) {
                    if ($modal.data('listing-helper-price-key') !== priceKey) {
                        return;
                    }

                    $modal.data('listing-helper-prices', response.prices || {});
                    updateListingHelper($modal);
                }).fail(function () {
                    var warnings = $modal.data('listing-helper-extra-warnings') || [];

                    warnings.push('Could not refresh local market prices. Output is based on purchase cost plus markup only.');
                    $modal.data('listing-helper-extra-warnings', warnings);
                    renderListingHelper($modal, parseListingHelperTransactions($modal.find('.market-seeding-listing-helper-input').val()), $modal.data('listing-helper-prices') || {});
                });
            }

            function parseListingHelperTransactions(text) {
                var result = {
                    items: {},
                    skipped: 0,
                    transactionCount: 0
                };

                String(text || '').split(/\r?\n/).forEach(function (line) {
                    line = $.trim(line);

                    if (!line) {
                        return;
                    }

                    var columns = line.split('\t');

                    var isCharacterLog = columns.length === 7;
                    var isCorporationLog = columns.length >= 9;

                    if (!isCharacterLog && !isCorporationLog) {
                        result.skipped++;
                        return;
                    }

                    var quantity = parseNumber(columns[1]);
                    var itemName = $.trim(columns[2]);
                    var unitCost = parseMoney(columns[3]);

                    if (!itemName || quantity <= 0 || unitCost <= 0) {
                        result.skipped++;
                        return;
                    }

                    if (!result.items[itemName]) {
                        result.items[itemName] = {
                            name: itemName,
                            quantity: 0,
                            highestCost: 0,
                            transactionCount: 0
                        };
                    }

                    result.items[itemName].quantity += quantity;
                    result.items[itemName].highestCost = Math.max(result.items[itemName].highestCost, unitCost);
                    result.items[itemName].transactionCount++;
                    result.transactionCount++;
                });

                return result;
            }

            function renderListingHelper($modal, parsed, prices) {
                var markup = parseFloat($modal.find('.market-seeding-listing-helper-markup').val() || 0);
                var salesTax = parseFloat($modal.find('.market-seeding-listing-helper-tax').val() || 0);
                var brokerFee = parseFloat($modal.find('.market-seeding-listing-helper-broker').val() || 0);
                var feeRate = Math.max(0, (salesTax + brokerFee) / 100);
                var useCompetitive = $modal.find('.market-seeding-listing-helper-competitive').is(':checked');
                var excludeProblemItems = $modal.find('.market-seeding-listing-helper-exclude-problem-items').is(':checked');
                var lines = [];
                var warnings = [];
                var stats = {
                    uniqueItems: 0,
                    quantity: 0,
                    value: 0,
                    profit: 0,
                    fees: 0,
                    competitive: 0,
                    unknown: 0,
                    noLocal: 0,
                    belowBreakEven: 0
                };
                var reviewRows = [];

                if (parsed.skipped) {
                    warnings.push(parsed.skipped + ' transaction line(s) could not be parsed.');
                }

                $.each(parsed.items, function (itemName, item) {
                    var priceInfo = prices[itemName] || {};
                    var markupPrice = roundUpToEvePrice(item.highestCost * (1 + (markup / 100)));
                    var localUndercutPrice = priceInfo.local_price ? previousEvePrice(parseFloat(priceInfo.local_price)) : null;
                    var competitivePrice = useCompetitive ? localUndercutPrice : null;
                    var sellPrice = competitivePrice ? Math.min(markupPrice, competitivePrice) : markupPrice;
                    var gross = sellPrice * item.quantity;
                    var basis = item.highestCost * item.quantity;
                    var fees = gross * feeRate;
                    var profit = gross - basis - fees;
                    var profitPercent = basis > 0 ? (profit / basis) * 100 : 0;

                    var usedCompetitive = competitivePrice && sellPrice === competitivePrice;

                    var notes = [];

                    var isUnknown = priceInfo.found === false;
                    var isBelowBreakEven = profit < 0;

                    if (isUnknown) {
                        stats.unknown++;
                        notes.push({ label: 'SDE missing', className: 'badge-danger' });
                    } else if (useCompetitive && !priceInfo.local_price) {
                        stats.noLocal++;
                        notes.push({ label: 'No local sell', className: 'badge-info' });
                    }

                    if (isBelowBreakEven) {
                        stats.belowBreakEven++;
                        notes.push({ label: 'Below break-even', className: 'badge-danger' });
                    }

                    if (!useCompetitive && localUndercutPrice && sellPrice > localUndercutPrice) {
                        notes.push({ label: 'Above local lowest', className: 'badge-warning' });
                    }

                    if (!notes.length) {
                        notes.push({
                            label: usedCompetitive ? 'Competitive' : 'Markup',
                            className: usedCompetitive ? 'badge-primary' : 'badge-success'
                        });
                    }

                    reviewRows.push({
                        name: item.name,
                        quantity: item.quantity,
                        highestCost: item.highestCost,
                        sellPrice: sellPrice,
                        localPrice: priceInfo.local_price ? parseFloat(priceInfo.local_price) : null,
                        jitaPrice: priceInfo.jita_price ? parseFloat(priceInfo.jita_price) : null,
                        profit: profit,
                        profitPercent: profitPercent,
                        notes: notes
                    });

                    if (!excludeProblemItems || (!isUnknown && !isBelowBreakEven)) {
                        stats.uniqueItems++;
                        stats.quantity += item.quantity;
                        stats.value += gross;
                        stats.fees += fees;
                        stats.profit += profit;
                        if (usedCompetitive) {
                            stats.competitive++;
                        }
                        lines.push(item.name + ' ' + formatEvePrice(sellPrice));
                    }
                });

                ($modal.data('listing-helper-extra-warnings') || []).forEach(function (warning) {
                    warnings.push(warning);
                });
                $modal.removeData('listing-helper-extra-warnings');

                $modal.find('.market-seeding-listing-helper-output').val(lines.join('\n'));
                $modal.find('[data-listing-helper-stat="items"]').text(numberWithCommas(stats.uniqueItems));
                $modal.find('[data-listing-helper-stat="quantity"]').text(numberWithCommas(stats.quantity));
                $modal.find('[data-listing-helper-stat="value"]').text(formatMetricMoney(stats.value));
                $modal.find('[data-listing-helper-stat="profit"]').text(formatSignedMoney(stats.profit));
                $modal.find('[data-listing-helper-stat="fees"]').text(formatMetricMoney(stats.fees));
                $modal.find('[data-listing-helper-stat="competitive"]').text(numberWithCommas(stats.competitive));

                var $warning = $modal.find('.market-seeding-listing-helper-warning');

                if (warnings.length) {
                    $warning.removeClass('d-none').html(warnings.map(escapeHtml).join('<br>'));
                } else {
                    $warning.addClass('d-none').empty();
                }

                renderListingHelperReview($modal, reviewRows);
            }

            function renderListingHelperReview($modal, rows) {
                var $panel = $modal.find('.market-seeding-listing-helper-review');
                var $table = $panel.find('.market-seeding-listing-helper-review-table');
                var $body = $table.find('tbody');

                if ($.fn.DataTable && $.fn.DataTable.isDataTable($table[0])) {
                    $table.DataTable().destroy();
                }

                $body.empty();

                if (!rows.length) {
                    $panel.addClass('d-none');
                    return;
                }

                rows.sort(function (a, b) {
                    var aProblem = a.notes.some(function (note) { return note.className === 'badge-danger' || note.className === 'badge-info'; });
                    var bProblem = b.notes.some(function (note) { return note.className === 'badge-danger' || note.className === 'badge-info'; });

                    if (aProblem !== bProblem) {
                        return aProblem ? -1 : 1;
                    }

                    return a.name.localeCompare(b.name);
                });

                rows.forEach(function (row) {
                    var noteHtml = row.notes.map(function (note) {
                        return '<span class="badge ' + note.className + '">' + escapeHtml(note.label) + '</span>';
                    }).join(' ');

                    var localPrice = row.localPrice ? formatMetricMoney(row.localPrice) : '<span class="text-muted">None</span>';
                    var profitClass = row.profit < 0 ? 'text-danger' : 'text-success';

                    $body.append(
                        '<tr>' +
                            '<td>' +
                                '<strong>' + escapeHtml(row.name) + '</strong>' +
                                (row.jitaPrice ? '<div class="text-muted small">Jita ' + formatMetricMoney(row.jitaPrice) + '</div>' : '') +
                            '</td>' +
                            '<td class="text-right" data-order="' + row.quantity + '">' + numberWithCommas(row.quantity) + '</td>' +
                            '<td class="text-right" data-order="' + row.highestCost + '">' + formatMetricMoney(row.highestCost) + '</td>' +
                            '<td class="text-right" data-order="' + row.sellPrice + '"><strong>' + formatMetricMoney(row.sellPrice) + '</strong></td>' +
                            '<td class="text-right" data-order="' + (row.localPrice || 0) + '">' + localPrice + '</td>' +
                            '<td class="text-right ' + profitClass + '" data-order="' + row.profit + '">' + formatSignedMoney(row.profit) + '</td>' +
                            '<td class="text-right ' + profitClass + '" data-order="' + row.profitPercent + '">' + formatPercent(row.profitPercent) + '</td>' +
                            '<td>' + noteHtml + '</td>' +
                        '</tr>'
                    );
                });

                $panel.removeClass('d-none');

                if ($.fn.DataTable) {
                    $table.DataTable({
                        order: [],
                        paging: true,
                        deferRender: true,
                        pageLength: 10,
                        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
                        autoWidth: false,
                        stateSave: false,
                        language: {
                            emptyTable: 'Paste wallet transactions to review listing helper pricing.',
                            zeroRecords: 'No item review rows match this search.'
                        }
                    });
                }
            }

            function previousEvePrice(price) {
                price = Math.max(0.01, parseFloat(price || 0));

                var tick = evePriceTick(price);

                return Math.max(0.01, Math.floor(((price - tick) / tick) + 0.0000001) * tick);
            }

            function roundUpToEvePrice(price) {
                price = Math.max(0.01, parseFloat(price || 0));

                var tick = evePriceTick(price);

                return Math.ceil((price / tick) - 0.0000001) * tick;
            }

            function evePriceTick(price) {
                price = Math.max(0.01, parseFloat(price || 0));

                return Math.max(0.01, Math.pow(10, Math.floor(Math.log10(price)) - 3));
            }

            function parseNumber(value) {
                return parseInt(String(value || '').replace(/,/g, ''), 10) || 0;
            }

            function parseMoney(value) {
                return parseFloat(String(value || '').replace(/ISK/ig, '').replace(/,/g, '').replace(/\s/g, '')) || 0;
            }

            function parsePositiveDecimal(value) {
                var parsed = parseFloat(String(value || '').replace(/,/g, '').replace(/\s/g, ''));

                return parsed > 0 ? parsed : null;
            }

            function formatEvePrice(value) {
                return Number(value || 0).toFixed(2);
            }

            function formatSignedMoney(value) {
                var number = Number(value || 0);
                var prefix = number > 0 ? '+' : (number < 0 ? '-' : '');

                return prefix + Math.abs(number).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }) + ' ISK';
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
