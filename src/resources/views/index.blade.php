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
            return implode("\n", [
                $priority['label'] . ' priority: ' . $priority['score'],
                'Status: +' . $priority['status_score'],
                'Missing: ' . $percent($priority['missing_percent']) . ' = +' . $priority['coverage_score'],
                'Sales: ' . $whole($priority['estimated_sold_quantity']) . ' / ' . $whole($priority['sales_window_days']) . ' days = +' . $priority['sales_score'],
                'Value: ' . $isk($priority['restock_cost']) . ' = +' . $priority['value_score'],
                'Total: ' . $priority['status_score'] . ' + ' . $priority['coverage_score'] . ' + ' . $priority['sales_score'] . ' + ' . $priority['value_score'] . ' = ' . $priority['score'],
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
        $sourceFilters = $stockRows
            ->flatMap(fn ($row) => $row['source_filters'] ?? [])
            ->unique('key')
            ->sortBy(fn ($source) => ($source['type'] ?? '') . '|' . ($source['label'] ?? ''))
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
    @include('seat-market-seeding::partials.fit-review-styles')

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
            flex: 1 1 0;
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
            flex: 1 1 145px;
            min-width: 135px;
        }
        .market-seeding-filter-field.is-wide {
            flex-basis: 240px;
            min-width: 210px;
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
            flex-wrap: wrap;
            gap: .4rem;
            justify-content: flex-end;
        }
        .market-seeding-filter-actions .btn {
            margin: 0;
            white-space: nowrap;
        }
        .market-seeding-controls-actions {
            align-items: center;
            display: flex;
            flex: 0 0 auto;
            flex-wrap: wrap;
            gap: .35rem;
            justify-content: flex-end;
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
        .market-seeding-category-readiness {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 1rem;
            padding: .75rem;
        }
        .market-seeding-category-readiness-header {
            align-items: center;
            display: flex;
            gap: .5rem;
            justify-content: space-between;
            margin-bottom: .65rem;
        }
        .market-seeding-category-readiness-header strong {
            font-size: .85rem;
            letter-spacing: .03em;
            text-transform: uppercase;
        }
        .market-seeding-category-readiness-header span {
            color: #6c757d;
            font-size: .78rem;
        }
        .market-seeding-category-readiness-grid {
            display: grid;
            gap: .55rem;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        }
        .market-seeding-category-readiness-card {
            background: #f8f9fa;
            border: 1px solid #e3e7ea;
            border-radius: 7px;
            padding: .55rem .65rem;
        }
        .market-seeding-category-readiness-title {
            align-items: center;
            display: flex;
            gap: .5rem;
            justify-content: space-between;
            margin-bottom: .4rem;
        }
        .market-seeding-category-readiness-name {
            font-weight: 700;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .market-seeding-category-readiness-score {
            font-size: .75rem;
            font-weight: 800;
        }
        .market-seeding-category-readiness-bar {
            background: #e9ecef;
            border-radius: 999px;
            height: 6px;
            margin-bottom: .4rem;
            overflow: hidden;
        }
        .market-seeding-category-readiness-fill {
            height: 100%;
            transition: width .16s ease;
        }
        .market-seeding-category-readiness-meta {
            color: #6c757d;
            display: flex;
            flex-wrap: wrap;
            font-size: .72rem;
            gap: .35rem .6rem;
        }
        .market-seeding-priority-badge {
            cursor: help;
        }
        .market-seeding-priority-tooltip {
            background: #1f2d33;
            border: 1px solid rgba(255, 255, 255, .16);
            border-radius: 6px;
            box-shadow: 0 10px 28px rgba(0, 0, 0, .28);
            color: #f4f7f9;
            display: none;
            font-size: .78rem;
            line-height: 1.35;
            max-width: 360px;
            padding: .65rem .75rem;
            pointer-events: none;
            position: fixed;
            text-align: left;
            white-space: pre-line;
            z-index: 3000;
        }
        .market-seeding-local-purchase-badge {
            margin-left: .35rem;
        }
        .market-seeding-locally-purchased {
            background: rgba(40, 167, 69, .08);
        }
        .market-seeding-restock-output-actions {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            justify-content: flex-end;
            margin-top: .65rem;
        }
        .market-seeding-purchased-tools {
            background: rgba(248, 249, 250, .92);
            border: 1px solid rgba(108, 117, 125, .22);
            border-radius: 8px;
            margin-top: 1.25rem;
            padding: 1rem;
        }
        .market-seeding-purchased-header {
            align-items: flex-start;
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            justify-content: space-between;
        }
        .market-seeding-purchased-import {
            display: grid;
            gap: .75rem;
            grid-template-columns: minmax(0, 1fr) minmax(220px, auto);
        }
        .market-seeding-purchased-import-actions {
            align-content: end;
            display: grid;
            gap: .5rem;
        }
        .market-seeding-purchased-summary {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            margin: .75rem 0;
        }
        .market-seeding-purchased-summary span {
            background: rgba(0, 123, 255, .08);
            border: 1px solid rgba(0, 123, 255, .16);
            border-radius: 6px;
            padding: .35rem .55rem;
        }
        .market-seeding-purchased-table input {
            max-width: 110px;
        }
        .market-seeding-purchased-import-result {
            margin-top: .5rem;
        }
        .market-seeding-purchased-item {
            align-items: center;
            display: flex;
            gap: .55rem;
        }
        .market-seeding-purchased-item-icon {
            align-items: center;
            background: rgba(108, 117, 125, .14);
            border-radius: 6px;
            display: inline-flex;
            flex: 0 0 32px;
            height: 32px;
            justify-content: center;
            overflow: hidden;
            width: 32px;
        }
        .market-seeding-purchased-item-icon img {
            display: block;
            height: 32px;
            width: 32px;
        }
        @media (max-width: 767px) {
            .market-seeding-purchased-import {
                grid-template-columns: 1fr;
            }
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
        .market-seeding-listing-helper-mode {
            background: #eef3f7;
            border: 1px solid #dee2e6;
            border-radius: 999px;
            display: inline-flex;
            gap: .25rem;
            margin-bottom: .75rem;
            padding: .25rem;
        }
        .market-seeding-listing-helper-mode label {
            border-radius: 999px;
            cursor: pointer;
            font-size: .82rem;
            font-weight: 700;
            margin: 0;
            padding: .35rem .7rem;
        }
        .market-seeding-listing-helper-mode input {
            display: none;
        }
        .market-seeding-listing-helper-mode input:checked + span {
            background: #17a2b8;
            border-radius: 999px;
            color: #fff;
            display: block;
            margin: -.35rem -.7rem;
            padding: .35rem .7rem;
        }
        .market-seeding-listing-helper-smart {
            border-top: 1px solid #dee2e6;
            margin-top: .75rem;
            padding-top: .75rem;
        }
        .market-seeding-listing-helper-smart-grid {
            display: grid;
            gap: .6rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
        .market-seeding-listing-helper-smart-heading {
            align-items: flex-start;
            display: flex;
            gap: .75rem;
            justify-content: space-between;
            margin-bottom: .65rem;
        }
        .market-seeding-listing-helper-smart-note {
            font-size: .8rem;
            margin-bottom: 0;
        }
        .market-seeding-listing-helper-tier-grid {
            display: grid;
            gap: .5rem;
            grid-template-columns: repeat(5, minmax(110px, 1fr));
            margin-top: .65rem;
        }
        .market-seeding-listing-helper-tier-grid label {
            color: #6c757d;
            display: block;
            font-size: .72rem;
            font-weight: 700;
            margin-bottom: .2rem;
            text-transform: uppercase;
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
            .market-seeding-listing-helper-stat-grid,
            .market-seeding-listing-helper-smart-grid,
            .market-seeding-listing-helper-tier-grid {
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
        .market-seeding-source-fitting {
            background: rgba(111, 66, 193, .16);
            color: #59359a;
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
        .market-seeding-dark-skin .market-seeding-category-readiness {
            background: #1f292e;
            border-color: #3c4b54;
        }
        .market-seeding-dark-skin .market-seeding-category-readiness-card {
            background: #1f2d33;
            border-color: #3c4b54;
        }
        .market-seeding-dark-skin .market-seeding-category-readiness-header span,
        .market-seeding-dark-skin .market-seeding-category-readiness-meta {
            color: #b8c7ce;
        }
        .market-seeding-dark-skin .market-seeding-category-readiness-bar {
            background: #34464f;
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
        .market-seeding-dark-skin .market-seeding-source-fitting {
            background: rgba(111, 66, 193, .32);
            color: #d8b4fe;
        }
        .market-seeding-dark-skin .market-seeding-locally-purchased {
            background: rgba(40, 167, 69, .14);
        }
        .market-seeding-dark-skin .market-seeding-purchased-tools {
            background: #1f2d33;
            border-color: rgba(60, 141, 188, .32);
        }
        .market-seeding-dark-skin .market-seeding-purchased-summary span {
            background: rgba(60, 141, 188, .18);
            border-color: rgba(60, 141, 188, .32);
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
        .market-seeding-modal.market-seeding-dark-skin .market-seeding-listing-helper-mode {
            background: #172228;
            border-color: #3c4b54;
        }
        .market-seeding-modal.market-seeding-dark-skin .market-seeding-listing-helper-smart {
            border-color: #3c4b54;
        }
        .market-seeding-modal.market-seeding-dark-skin .market-seeding-listing-helper-tier-grid label {
            color: #b8c7ce;
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
        @include('seat-market-seeding::partials.expiring-orders-alert')

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
                    <span class="info-box-text">Empty/Low Status</span>
                    <span class="info-box-number">{{ $whole($totals['empty_lines']) }} / {{ $whole($totals['low_lines']) }}</span>
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
                        <div class="market-seeding-filter-field is-wide">
                            <label for="market-seeding-source-filter">Doctrine / Fit</label>
                            <select class="form-control form-control-sm" id="market-seeding-source-filter">
                                <option value="">All Doctrines / Fits</option>
                                @foreach($sourceFilters as $sourceFilter)
                                    <option value="{{ $sourceFilter['key'] }}">
                                        {{ ($sourceFilter['type'] ?? '') === 'doctrine' ? 'Doctrine' : 'Saved Fit' }}: {{ $sourceFilter['label'] }}
                                    </option>
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
                    ->map(function ($row) use ($market) {
                        $sourceFilterKeys = collect($row['source_filters'] ?? [])->pluck('key')->values()->all();

                        return [
                            'category' => $row['type_category'],
                            'group' => $row['type_group'] ?? 'Unknown',
                            'status' => $row['stock_status'],
                            'priority' => $row['priority']['level'],
                            'source_filters' => $sourceFilterKeys,
                            'type_id' => $row['item']->type_id,
                            'name' => $row['item']->type_name,
                            'quantity' => $row['missing_quantity'],
                            'line' => $row['export_line'],
                            'volume' => $row['restock_volume'],
                            'unit_volume' => $row['missing_quantity'] > 0
                                ? $row['restock_volume'] / $row['missing_quantity']
                                : 0,
                            'unit_value' => $row['missing_quantity'] > 0
                                ? $row['restock_cost'] / $row['missing_quantity']
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
                            Empty/Low <span data-market-metric="header-empty">{{ $whole($marketReport['totals']['empty_lines']) }}</span> / <span data-market-metric="header-low">{{ $whole($marketReport['totals']['low_lines']) }}</span> line(s) &middot;
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
                                <span>Empty/Low Status</span>
                                <strong><span data-market-metric="empty">{{ $whole($marketReport['totals']['empty_lines']) }}</span> / <span data-market-metric="low">{{ $whole($marketReport['totals']['low_lines']) }}</span> lines</strong>
                            </div>
                        </div>
                        <div class="market-seeding-category-readiness" data-market-category-readiness>
                            <div class="market-seeding-category-readiness-header">
                                <strong>Readiness by Category</strong>
                                <span>Uses the current dashboard filters</span>
                            </div>
                            <div class="market-seeding-category-readiness-grid"></div>
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
                                        <th>Source</th>
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
                                            data-source-filters="{{ implode(' ', collect($row['source_filters'] ?? [])->pluck('key')->all()) }}"
                                            data-market-id="{{ $market->id }}"
                                            data-type-id="{{ $row['item']->type_id }}"
                                            data-item-name="{{ $row['item']->type_name }}"
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
                                            <td>{{ ' ' . implode(' ', collect($row['source_filters'] ?? [])->pluck('key')->all()) . ' ' }}</td>
                                            <td data-order="{{ $row['priority']['score'] }}">
                                                <span class="badge {{ $priorityBadge($row['priority']['level']) }} market-seeding-priority-badge"
                                                      data-priority-tooltip="{{ $priorityTooltip($row['priority']) }}"
                                                      aria-label="{{ $priorityTooltip($row['priority']) }}">
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
                                This list follows the dashboard filters.
                                Estimated restock volume: <span class="market-seeding-export-volume">{{ $volume($marketReport['totals']['restock_volume']) }}</span> m&sup3;
                            </p>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="{{ $exportId }}-freight-limit">Remaining Freight Space</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control market-seeding-freight-limit" id="{{ $exportId }}-freight-limit" placeholder="Optional, e.g. 40,000">
                                            <div class="input-group-append">
                                                <span class="input-group-text">m&sup3;</span>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">Trim the list to fit inside this remaining cargo space.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="{{ $exportId }}-value-limit">Maximum ISK Value</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control market-seeding-value-limit" id="{{ $exportId }}-value-limit" placeholder="Optional, e.g. 2,000,000,000">
                                            <div class="input-group-append">
                                                <span class="input-group-text">ISK</span>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">Trim the list so estimated restock cost stays under this value.</small>
                                    </div>
                                </div>
                            </div>
                            <small class="form-text text-muted market-seeding-limit-result d-none"></small>
                            <textarea id="{{ $exportId }}" class="form-control market-seeding-export-textarea" rows="10" readonly data-lines='@json($restockLines)'>{{ $marketReport['export'] }}</textarea>
                            <div class="market-seeding-restock-output-actions">
                                <button type="button" class="btn btn-sm btn-outline-success market-seeding-mark-visible-purchased">
                                    <i class="fas fa-check"></i> Mark Current List Purchased
                                </button>
                                <button type="button" class="btn btn-sm btn-primary copy-market-export" data-target="{{ $exportId }}">
                                    <i class="fas fa-copy"></i> Copy Multi-Buy
                                </button>
                            </div>
                            <div class="market-seeding-purchased-tools" data-market-id="{{ $market->id }}">
                                <div class="market-seeding-purchased-header">
                                    <div>
                                        <strong>Temporarily Purchased</strong>
                                        <small class="text-muted d-block">Stored only in this browser. These quantities are subtracted from this restock list, but they do not change real market stock or health.</small>
                                    </div>
                                </div>
                                <div class="market-seeding-purchased-import mt-3">
                                    <div class="form-group mb-0">
                                        <label>Paste Purchased Items</label>
                                        <textarea class="form-control market-seeding-purchased-import-text" rows="4" placeholder="Paste restock list, market transactions, or inventory rows..."></textarea>
                                        <small class="form-text text-muted">Supports restock list format, market transaction logs, and inventory rows with item name plus quantity.</small>
                                        <div class="market-seeding-purchased-import-result d-none"></div>
                                    </div>
                                    <div class="market-seeding-purchased-import-actions">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input market-seeding-purchased-replace" id="{{ $exportId }}-replace-purchased">
                                            <label class="custom-control-label" for="{{ $exportId }}-replace-purchased">Replace current list</label>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-primary market-seeding-import-purchased">
                                            <i class="fas fa-plus"></i> Add Purchased
                                        </button>
                                    </div>
                                </div>
                                <div class="market-seeding-purchased-summary">
                                    <span>Items: <strong data-purchased-summary="items">0</strong></span>
                                    <span>Total m&sup3;: <strong data-purchased-summary="volume">0.00</strong></span>
                                    <span>Total Value: <strong data-purchased-summary="value">0.00 ISK</strong></span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover market-seeding-purchased-table mb-0">
                                        <thead>
                                            <tr>
                                                <th>Module</th>
                                                <th class="text-right">Quantity</th>
                                                <th class="text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr class="market-seeding-purchased-empty-row">
                                                <td colspan="3" class="text-muted">No temporary purchases marked for this market.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-right mt-3">
                                    <button type="button" class="btn btn-sm btn-outline-danger market-seeding-clear-purchased">
                                        <i class="fas fa-trash"></i> Clear Temporary Purchases
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
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
                                        Paste a character or corporation market transaction log, or paste <code>Item Name&lt;tab&gt;quantity</code> rows when no purchase prices are available.
                                        Purchase rows are grouped, sell rows are ignored, the highest unit cost is used, and output is generated as <code>Item Name price</code>.
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
                                            <small class="form-text text-muted">Supports character logs with 7 columns, corporation logs with 9 columns, and item + quantity rows. Only market transaction rows with a negative total cost are used.</small>
                                        </div>
                                    </div>
                                    <div class="market-seeding-listing-helper-panel">
                                        <div class="market-seeding-listing-helper-section-title">
                                            <i class="fas fa-sliders-h"></i> Pricing Rules
                                        </div>
                                        <div class="market-seeding-listing-helper-mode">
                                            <label>
                                                <input type="radio" class="market-seeding-listing-helper-mode-input" name="{{ $exportId }}-listing-helper-mode" value="simple" checked>
                                                <span>Simple markup</span>
                                            </label>
                                            <label>
                                                <input type="radio" class="market-seeding-listing-helper-mode-input" name="{{ $exportId }}-listing-helper-mode" value="smart">
                                                <span>Smart markup</span>
                                            </label>
                                        </div>
                                        <div class="market-seeding-listing-helper-settings">
                                            <div class="form-group market-seeding-listing-helper-simple-only">
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
                                                    Only copy ready-to-list items
                                                    <small class="text-muted">Skip SDE-missing, missing Jita cost basis, and below break-even items in the copy box.</small>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="market-seeding-listing-helper-smart d-none">
                                            <div class="market-seeding-listing-helper-smart-heading">
                                                <div class="text-muted market-seeding-listing-helper-smart-note">
                                                    Uses category markups for ammo, ships, and drones. Other items use the markup curve based on unit cost.
                                                </div>
                                                <button type="button"
                                                        class="btn btn-xs btn-outline-secondary market-seeding-listing-helper-smart-reset"
                                                        title="Reset smart markup defaults"
                                                        data-toggle="tooltip">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            </div>
                                            <div class="market-seeding-listing-helper-smart-grid">
                                                <div class="form-group">
                                                    <label>Ammo Markup</label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control market-seeding-listing-helper-smart-ammo" value="15" step="0.01" min="0">
                                                        <div class="input-group-append">
                                                            <span class="input-group-text">%</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label>Ship Markup</label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control market-seeding-listing-helper-smart-ships" value="10" step="0.01" min="0">
                                                        <div class="input-group-append">
                                                            <span class="input-group-text">%</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label>Drone Markup</label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control market-seeding-listing-helper-smart-drones" value="25" step="0.01" min="0">
                                                        <div class="input-group-append">
                                                            <span class="input-group-text">%</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label>Minimum Profit / Unit</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control market-seeding-listing-helper-smart-floor" value="50,000">
                                                        <div class="input-group-append">
                                                            <span class="input-group-text">ISK</span>
                                                        </div>
                                                    </div>
                                                    <small class="form-text text-muted">Ensures cheap non-ammo items make at least this much profit per unit before fees.</small>
                                                </div>
                                                <div class="custom-control custom-checkbox market-seeding-listing-helper-option">
                                                    <input type="checkbox" class="custom-control-input market-seeding-listing-helper-smart-floor-skip-ammo" id="{{ $exportId }}-listing-helper-floor-skip-ammo" checked>
                                                    <label class="custom-control-label" for="{{ $exportId }}-listing-helper-floor-skip-ammo">
                                                        Do not apply minimum profit to ammo
                                                        <small class="text-muted">Ammo is usually bought in bulk, so per-unit profit floors can get silly fast.</small>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <a class="small" data-toggle="collapse" href="#{{ $exportId }}-listing-helper-smart-advanced" role="button" aria-expanded="false" aria-controls="{{ $exportId }}-listing-helper-smart-advanced">
                                                    Advanced markup percentage curve
                                                </a>
                                                <div class="collapse" id="{{ $exportId }}-listing-helper-smart-advanced">
                                                    <div class="text-muted small mt-2 mb-2">
                                                        These values are markup percentages used when the item is not ammo, a ship, or a drone.
                                                    </div>
                                                    <div class="market-seeding-listing-helper-tier-grid">
                                                        <div>
                                                            <label>Under 25k</label>
                                                            <div class="input-group">
                                                                <input type="number" class="form-control market-seeding-listing-helper-smart-tier" data-tier="under25k" value="300" step="0.01" min="0">
                                                                <div class="input-group-append">
                                                                    <span class="input-group-text">%</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <label>25k - 1m</label>
                                                            <div class="input-group">
                                                                <input type="number" class="form-control market-seeding-listing-helper-smart-tier" data-tier="under1m" value="100" step="0.01" min="0">
                                                                <div class="input-group-append">
                                                                    <span class="input-group-text">%</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <label>1m - 20m</label>
                                                            <div class="input-group">
                                                                <input type="number" class="form-control market-seeding-listing-helper-smart-tier" data-tier="under20m" value="50" step="0.01" min="0">
                                                                <div class="input-group-append">
                                                                    <span class="input-group-text">%</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <label>20m - 100m</label>
                                                            <div class="input-group">
                                                                <input type="number" class="form-control market-seeding-listing-helper-smart-tier" data-tier="under100m" value="20" step="0.01" min="0">
                                                                <div class="input-group-append">
                                                                    <span class="input-group-text">%</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <label>Over 100m</label>
                                                            <div class="input-group">
                                                                <input type="number" class="form-control market-seeding-listing-helper-smart-tier" data-tier="over100m" value="10" step="0.01" min="0">
                                                                <div class="input-group-append">
                                                                    <span class="input-group-text">%</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
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
                                                <th class="text-right">Unit Cost Basis</th>
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
        'canViewMarketOrderOwners' => auth()->user()->can('seat-market-seeding.seeders'),
    ])
    </div>
@endsection

@push('javascript')
    @include('seat-market-seeding::partials.fit-review-scripts')

    <script>
        $(function () {
            var dashboardTables = null;
            var dashboardItemDetails = @json($dashboardItemDetails);
            var listingHelperCsrfToken = '{{ csrf_token() }}';
            var listingHelperPreferenceKey = 'seat-market-seeding.listing-helper.preferences.v1';
            var temporaryPurchaseKey = 'seat-market-seeding.temporary-purchases.v1';
            var listingHelperSmartDefaults = {
                ammoMarkup: 15,
                shipMarkup: 10,
                droneMarkup: 25,
                floor: 50000,
                skipAmmoFloor: true,
                tiers: {
                    under25k: 300,
                    under1m: 100,
                    under20m: 50,
                    under100m: 20,
                    over100m: 10
                }
            };
            var dashboardSourceFilter = '';

            if ($.fn.DataTable) {
                $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                    if (!$(settings.nTable).hasClass('market-seeding-dashboard-table') || !dashboardSourceFilter) {
                        return true;
                    }

                    var row = settings.aoData && settings.aoData[dataIndex]
                        ? settings.aoData[dataIndex].nTr
                        : null;

                    if (!row) {
                        return true;
                    }

                    return matchesDashboardSourceFilter($(row).data('source-filters'), dashboardSourceFilter);
                });

                dashboardTables = $('.market-seeding-dashboard-table').DataTable({
                    order: [[0, 'asc']],
                    paging: true,
                    pageLength: 25,
                    lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
                    stateSave: true,
                    autoWidth: false,
                    columnDefs: [
                        { targets: [1, 2, 3, 4, 5], visible: false }
                    ],
                    stateSaveParams: function (settings, data) {
                        data.marketSeedingSchema = 11;

                        if (data.columns) {
                            [1, 2, 3, 4, 5].forEach(function (columnIndex) {
                                if (data.columns[columnIndex] && data.columns[columnIndex].search) {
                                    data.columns[columnIndex].search.search = '';
                                }
                            });
                        }
                    },
                    stateLoadParams: function (settings, data) {
                        return data.marketSeedingSchema === 11;
                    },
                    language: {
                        emptyTable: 'No stock targets have been configured for this market.',
                        zeroRecords: 'No items match this search.'
                    }
                });
                $('.market-seeding-dashboard-table').on('draw.dt', function () {
                    updateDashboardTemporaryPurchaseBadges();
                });
            }

            $('.copy-market-export').on('click', function () {
                var textarea = document.getElementById($(this).data('target'));
                textarea.select();
                document.execCommand('copy');
            });

            $('.market-seeding-listing-helper-modal').on('shown.bs.modal', function () {
                applyListingHelperPreferences($(this));
                updateListingHelperSmartVisibility($(this));
                scheduleListingHelperUpdate($(this), 0);
            });

            $('.market-seeding-listing-helper-modal').on('hidden.bs.modal', function () {
                resetListingHelper($(this));
            });

            $(document).on('input change', '.market-seeding-listing-helper-input, .market-seeding-listing-helper-markup, .market-seeding-listing-helper-tax, .market-seeding-listing-helper-broker, .market-seeding-listing-helper-competitive, .market-seeding-listing-helper-exclude-problem-items, .market-seeding-listing-helper-mode-input, .market-seeding-listing-helper-smart-ammo, .market-seeding-listing-helper-smart-ships, .market-seeding-listing-helper-smart-drones, .market-seeding-listing-helper-smart-floor, .market-seeding-listing-helper-smart-floor-skip-ammo, .market-seeding-listing-helper-smart-tier', function () {
                var $modal = $(this).closest('.market-seeding-listing-helper-modal');

                if ($(this).hasClass('market-seeding-listing-helper-smart-floor')) {
                    formatMoneyInput($(this), true);
                }

                if (!$(this).hasClass('market-seeding-listing-helper-input')) {
                    updateListingHelperSmartVisibility($modal);
                    saveListingHelperPreferences($modal);
                }

                scheduleListingHelperUpdate($modal, 250);
            });

            $(document).on('click', '.market-seeding-copy-listing-helper', function () {
                var textarea = $(this).closest('.market-seeding-listing-helper-modal').find('.market-seeding-listing-helper-output')[0];

                textarea.select();
                document.execCommand('copy');
            });

            $(document).on('click', '.market-seeding-listing-helper-smart-reset', function () {
                var $modal = $(this).closest('.market-seeding-listing-helper-modal');

                applyListingHelperSmartDefaults($modal);
                saveListingHelperPreferences($modal);
                scheduleListingHelperUpdate($modal, 0);
            });

            $(document).on('input change', '.market-seeding-freight-limit, .market-seeding-value-limit', function () {
                formatMoneyInput($(this), true);
                updateRestockExport($(this).closest('.market-seeding-modal'));
            });

            $(document).on('click', '.market-seeding-import-purchased', function () {
                var $tools = $(this).closest('.market-seeding-purchased-tools');
                var $modal = $(this).closest('.market-seeding-modal');
                var textarea = $modal.find('.market-seeding-export-textarea')[0];
                var lines = $(textarea).data('lines') || [];
                var importResult = parseTemporaryPurchaseImport($tools.find('.market-seeding-purchased-import-text').val(), lines);

                renderTemporaryPurchaseImportResult($tools, importResult.stats);

                if (!importResult.items.length) {
                    return;
                }

                saveTemporaryPurchases(
                    $tools.data('market-id'),
                    importResult.items,
                    $tools.find('.market-seeding-purchased-replace').is(':checked')
                );
                $tools.find('.market-seeding-purchased-import-text').val('');
                updateRestockExport($modal);
                updateDashboardTemporaryPurchaseBadges();
            });

            $(document).on('click', '.market-seeding-mark-visible-purchased', function () {
                var $modal = $(this).closest('.market-seeding-modal');
                var $tools = $modal.find('.market-seeding-purchased-tools');
                var selected = $modal.data('restock-selected-lines') || [];

                if (!selected.length) {
                    window.alert('No items are currently in the multi-buy list.');
                    return;
                }

                saveTemporaryPurchases(
                    $tools.data('market-id'),
                    $.map(selected, function (line) {
                        return {
                            type_id: line.type_id,
                            name: line.name,
                            unit_volume: line.unit_volume,
                            unit_value: line.unit_value,
                            quantity: Number(line.quantity || 0)
                        };
                    }),
                    false
                );
                updateRestockExport($modal);
                updateDashboardTemporaryPurchaseBadges();
            });

            $(document).on('click', '.market-seeding-clear-purchased', function () {
                var $tools = $(this).closest('.market-seeding-purchased-tools');

                if (!window.confirm('Clear all temporary purchases for this market in this browser?')) {
                    return;
                }

                clearTemporaryPurchasesForMarket($tools.data('market-id'));
                updateRestockExport($(this).closest('.market-seeding-modal'));
                updateDashboardTemporaryPurchaseBadges();
            });

            $(document).on('input change', '.market-seeding-purchased-quantity', function () {
                var $input = $(this);
                var $tools = $input.closest('.market-seeding-purchased-tools');
                var marketId = $tools.data('market-id');
                var purchaseKey = $input.closest('tr').data('purchase-key');
                var rawValue = $.trim($input.val());
                var quantity = parseNumber($input.val());

                if (rawValue === '') {
                    return;
                }

                updateTemporaryPurchaseQuantity(marketId, purchaseKey, quantity);
                updateRestockExport($input.closest('.market-seeding-modal'));
                updateDashboardTemporaryPurchaseBadges();
            });

            $(document).on('click', '.market-seeding-remove-purchased', function () {
                var $row = $(this).closest('tr');
                var $tools = $(this).closest('.market-seeding-purchased-tools');

                deleteTemporaryPurchase($tools.data('market-id'), $row.data('purchase-key'));
                updateRestockExport($(this).closest('.market-seeding-modal'));
                updateDashboardTemporaryPurchaseBadges();
            });

            $(document).on('keydown', '.market-seeding-remove-purchased', function (event) {
                if (event.key === 'Backspace' || event.key === 'Delete') {
                    event.preventDefault();
                }
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

            $('#market-seeding-source-filter').on('change', function () {
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
                $('#market-seeding-source-filter').val('');
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
            updateDashboardTemporaryPurchaseBadges();
            if ($.fn.tooltip) {
                $('[data-toggle="tooltip"]').tooltip({
                    container: 'body',
                    html: true
                });
            }
            initializePriorityTooltips();
            openDashboardItemFromHash();

            function initializePriorityTooltips() {
                if ($(document.body).data('market-seeding-priority-tooltips')) {
                    return;
                }

                $(document.body).data('market-seeding-priority-tooltips', true);

                var $tooltip = $('<div class="market-seeding-priority-tooltip" role="tooltip"></div>').appendTo(document.body);

                function positionTooltip(event) {
                    var margin = 14;
                    var left = event.clientX + margin;
                    var top = event.clientY + margin;

                    $tooltip.css({
                        left: 0,
                        top: 0,
                        display: 'block'
                    });

                    var width = $tooltip.outerWidth();
                    var height = $tooltip.outerHeight();

                    if (left + width + margin > window.innerWidth) {
                        left = Math.max(margin, event.clientX - width - margin);
                    }

                    if (top + height + margin > window.innerHeight) {
                        top = Math.max(margin, event.clientY - height - margin);
                    }

                    $tooltip.css({
                        left: left + 'px',
                        top: top + 'px'
                    });
                }

                $(document)
                    .on('mouseenter focusin', '.market-seeding-priority-badge', function (event) {
                        var text = $(this).attr('data-priority-tooltip');

                        if (!text) {
                            return;
                        }

                        $tooltip.text(text);
                        positionTooltip(event);
                    })
                    .on('mousemove', '.market-seeding-priority-badge', function (event) {
                        if ($tooltip.is(':visible')) {
                            positionTooltip(event);
                        }
                    })
                    .on('mouseleave focusout', '.market-seeding-priority-badge', function () {
                        $tooltip.hide().text('');
                    });
            }

            function applyDashboardFilters() {
                var typeCategory = $('#market-seeding-type-filter').val();
                var typeGroup = $('#market-seeding-group-filter').val();
                var sourceFilter = $('#market-seeding-source-filter').val();
                var stockStatus = $('#market-seeding-stock-status-filter').val();
                var priority = $('#market-seeding-priority-filter').val();

                dashboardSourceFilter = sourceFilter || '';

                $('.market-seeding-dashboard-table').each(function () {
                    if (!$.fn.DataTable || !$.fn.DataTable.isDataTable(this)) {
                        $(this).find('tbody tr').each(function () {
                            var matches = matchesDashboardFilters(
                                $(this).data('category'),
                                $(this).data('group'),
                                $(this).data('stock-status'),
                                $(this).data('priority'),
                                String($(this).data('source-filters') || ''),
                                typeCategory,
                                typeGroup,
                                sourceFilter,
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
                        .search(priority ? '^' + escapeRegex(priority) + '$' : '', true, false);
                    table
                        .column(5)
                        .search('', true, false)
                        .draw();
                });

                updateMarketMetricCards();
                updateAllRestockExports();
                updateDashboardTemporaryPurchaseBadges();
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
                        restockVolume: 0
                    };

                    rows.each(function () {
                        var $row = $(this);
                        var stockStatus = String($row.data('stock-status') || '');

                        totals.trackedLines++;
                        totals.lowLines += stockStatus === 'low' ? 1 : 0;
                        totals.emptyLines += stockStatus === 'empty' ? 1 : 0;
                        totals.seededValue += Number($row.data('seeded-value') || 0);
                        totals.desiredValue += Number($row.data('desired-value') || 0);
                        totals.restockCost += Number($row.data('restock-cost') || 0);
                        totals.restockVolume += Number($row.data('restock-volume') || 0);
                    });

                    var health = healthScoreFromLines(totals.lowLines, totals.emptyLines, totals.trackedLines);

                    $card.find('[data-market-metric="health"], [data-market-metric="header-health"]').text(formatPercent(health));
                    $card.find('[data-market-metric="seeded"]').text(formatMetricMoney(totals.seededValue));
                    $card.find('[data-market-metric="target"]').text(formatMetricMoney(totals.desiredValue));
                    $card.find('[data-market-metric="restock"]').text(formatMetricMoney(totals.restockCost));
                    $card.find('[data-market-metric="restock-volume"], [data-market-metric="header-restock-volume"]').text(formatDecimal(totals.restockVolume));
                    $card.find('[data-market-metric="empty"], [data-market-metric="header-empty"]').text(numberWithCommas(totals.emptyLines));
                    $card.find('[data-market-metric="low"], [data-market-metric="header-low"]').text(numberWithCommas(totals.lowLines));
                    $card.find('[data-market-metric="header-restock"]').text(formatMetricMoney(totals.restockCost));
                    updateMarketHealthBadge($card.find('.market-seeding-health-badge'), health);
                    updateMarketCategoryReadiness($card, rows);
                });
            }

            function updateMarketCategoryReadiness($card, rows) {
                var categories = {};

                rows.each(function () {
                    var $row = $(this);
                    var category = String($row.data('category') || 'Unknown');
                    var stockStatus = String($row.data('stock-status') || '');

                    if (!categories[category]) {
                        categories[category] = {
                            lines: 0,
                            low: 0,
                            empty: 0
                        };
                    }

                    categories[category].lines++;
                    categories[category].low += stockStatus === 'low' ? 1 : 0;
                    categories[category].empty += stockStatus === 'empty' ? 1 : 0;
                });

                var categoryNames = Object.keys(categories).sort();
                var $panel = $card.find('[data-market-category-readiness]');
                var $grid = $panel.find('.market-seeding-category-readiness-grid');

                if (!categoryNames.length) {
                    $panel.addClass('d-none');
                    $grid.empty();
                    return;
                }

                $panel.removeClass('d-none');
                $grid.html(categoryNames.map(function (category) {
                    var totals = categories[category];
                    var health = healthScoreFromLines(totals.low, totals.empty, totals.lines);
                    var fillClass = health >= 90 ? 'bg-success' : (health >= 60 ? 'bg-warning' : 'bg-danger');
                    var badgeClass = health >= 90 ? 'badge-success' : (health >= 60 ? 'badge-warning' : 'badge-danger');

                    return '<div class="market-seeding-category-readiness-card">' +
                        '<div class="market-seeding-category-readiness-title">' +
                            '<span class="market-seeding-category-readiness-name" title="' + escapeHtml(category) + '">' + escapeHtml(category) + '</span>' +
                            '<span class="badge ' + badgeClass + ' market-seeding-category-readiness-score">' + formatPercent(health) + '</span>' +
                        '</div>' +
                        '<div class="market-seeding-category-readiness-bar">' +
                            '<div class="market-seeding-category-readiness-fill ' + fillClass + '" style="width: ' + Math.max(0, Math.min(100, health)) + '%;"></div>' +
                        '</div>' +
                        '<div class="market-seeding-category-readiness-meta">' +
                            '<span>' + numberWithCommas(totals.lines) + ' lines</span>' +
                            '<span>' + numberWithCommas(totals.empty) + ' empty</span>' +
                            '<span>' + numberWithCommas(totals.low) + ' low</span>' +
                        '</div>' +
                    '</div>';
                }).join(''));
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
                var sourceFilter = $('#market-seeding-source-filter').val();
                var stockStatus = $('#market-seeding-stock-status-filter').val();
                var priority = $('#market-seeding-priority-filter').val();
                var lines = $(textarea).data('lines') || [];
                var filtered = $.grep(lines, function (line) {
                    return matchesDashboardFilters(line.category, line.group, line.status, line.priority, line.source_filters || [], typeCategory, typeGroup, sourceFilter, stockStatus, priority);
                });
                var marketId = modal.find('.market-seeding-purchased-tools').data('market-id');
                filtered = applyTemporaryPurchases(filtered, marketId);
                var freightLimit = parsePositiveDecimal(modal.find('.market-seeding-freight-limit').val());
                var valueLimit = parsePositiveMoney(modal.find('.market-seeding-value-limit').val());
                var selected = applyRestockLimits(filtered, freightLimit, valueLimit);
                var volume = selected.reduce(function (total, line) {
                    return total + Number(line.volume || 0);
                }, 0);
                var value = selected.reduce(function (total, line) {
                    return total + restockLineValue(line);
                }, 0);

                modal.data('restock-selected-lines', selected);
                textarea.value = $.map(selected, function (line) {
                    return line.line;
                }).join('\n');

                modal.find('.market-seeding-export-volume').text(formatDecimal(volume));
                updateLimitResult(modal, freightLimit, valueLimit, volume, value);
                renderTemporaryPurchases(modal, lines);
            }

            function updateLimitResult(modal, freightLimit, valueLimit, selectedVolume, selectedValue) {
                var result = modal.find('.market-seeding-limit-result');
                var parts = [];

                if ((!freightLimit || freightLimit <= 0) && (!valueLimit || valueLimit <= 0)) {
                    result.addClass('d-none').empty();
                    return;
                }

                if (freightLimit && freightLimit > 0) {
                    parts.push(
                        'Volume: <strong>' + formatDecimal(selectedVolume) + '</strong> / ' +
                        '<strong>' + formatDecimal(freightLimit) + '</strong> m&sup3; ' +
                        '(remaining <strong>' + formatDecimal(Math.max(0, freightLimit - selectedVolume)) + '</strong> m&sup3;)'
                    );
                }

                if (valueLimit && valueLimit > 0) {
                    parts.push(
                        'Value: <strong>' + formatIsk(selectedValue) + '</strong> / ' +
                        '<strong>' + formatIsk(valueLimit) + '</strong> ' +
                        '(remaining <strong>' + formatIsk(Math.max(0, valueLimit - selectedValue)) + '</strong>)'
                    );
                }

                result.removeClass('d-none').html('Filtered list totals: ' + parts.join(' &middot; '));
            }

            function temporaryPurchaseStorage() {
                try {
                    return JSON.parse(window.localStorage.getItem(temporaryPurchaseKey) || '{}') || {};
                } catch (error) {
                    return {};
                }
            }

            function saveTemporaryPurchaseStorage(storage) {
                try {
                    window.localStorage.setItem(temporaryPurchaseKey, JSON.stringify(storage || {}));
                } catch (error) {
                    window.alert('Unable to save temporary purchases in this browser.');
                }
            }

            function temporaryPurchaseKeyFor(marketId, purchaseKey) {
                return String(marketId) + ':' + String(purchaseKey);
            }

            function temporaryPurchaseItemKey(purchase) {
                if (Number(purchase.type_id || 0) > 0) {
                    return 'type:' + Number(purchase.type_id);
                }

                return 'name:' + normalizeTemporaryPurchaseName(purchase.name || '');
            }

            function normalizeTemporaryPurchaseName(name) {
                return String(name || '').trim().toLowerCase().replace(/\s+/g, ' ');
            }

            function purchasesForMarket(marketId) {
                var storage = temporaryPurchaseStorage();
                var prefix = String(marketId) + ':';
                var purchases = {};

                $.each(storage, function (key, purchase) {
                    if (key.indexOf(prefix) === 0 && Number(purchase.quantity || 0) >= 0) {
                        var purchaseKey = purchase.purchase_key;

                        if (!purchaseKey && Number(purchase.type_id || 0) > 0) {
                            purchaseKey = 'type:' + Number(purchase.type_id);
                        }

                        purchaseKey = purchaseKey || key.replace(prefix, '');
                        purchases[String(purchaseKey)] = $.extend({}, purchase, {
                            purchase_key: purchaseKey
                        });
                    }
                });

                return purchases;
            }

            function saveTemporaryPurchases(marketId, purchases, replace) {
                var storage = temporaryPurchaseStorage();
                var prefix = String(marketId) + ':';

                if (replace) {
                    $.each(Object.keys(storage), function (index, key) {
                        if (key.indexOf(prefix) === 0) {
                            delete storage[key];
                        }
                    });
                }

                $.each(purchases, function (index, purchase) {
                    var purchaseKey = temporaryPurchaseItemKey(purchase);
                    var quantity = Number(purchase.quantity || 0);

                    if (!purchaseKey || purchaseKey === 'name:' || quantity <= 0) {
                        return;
                    }

                    var key = temporaryPurchaseKeyFor(marketId, purchaseKey);
                    var existing = storage[key] || {};
                    storage[key] = {
                        market_id: Number(marketId),
                        purchase_key: purchaseKey,
                        type_id: Number(purchase.type_id || existing.type_id || 0),
                        name: purchase.name || existing.name || '',
                        unit_volume: Number(purchase.unit_volume || existing.unit_volume || 0),
                        unit_value: Number(purchase.unit_value || existing.unit_value || 0),
                        quantity: replace ? quantity : quantity + Number(existing.quantity || 0),
                        updated_at: new Date().toISOString()
                    };
                });

                saveTemporaryPurchaseStorage(storage);
            }

            function updateTemporaryPurchaseQuantity(marketId, purchaseKey, quantity) {
                var storage = temporaryPurchaseStorage();
                var key = temporaryPurchaseKeyFor(marketId, purchaseKey);

                storage[key] = $.extend({}, storage[key] || {}, {
                    market_id: Number(marketId),
                    purchase_key: purchaseKey,
                    quantity: Math.max(0, quantity),
                    updated_at: new Date().toISOString()
                });
                saveTemporaryPurchaseStorage(storage);
            }

            function deleteTemporaryPurchase(marketId, purchaseKey) {
                var storage = temporaryPurchaseStorage();
                var key = temporaryPurchaseKeyFor(marketId, purchaseKey);

                delete storage[key];
                saveTemporaryPurchaseStorage(storage);
            }

            function clearTemporaryPurchasesForMarket(marketId) {
                var storage = temporaryPurchaseStorage();
                var prefix = String(marketId) + ':';

                $.each(Object.keys(storage), function (index, key) {
                    if (key.indexOf(prefix) === 0) {
                        delete storage[key];
                    }
                });

                saveTemporaryPurchaseStorage(storage);
            }

            function applyTemporaryPurchases(lines, marketId) {
                var purchases = purchasesForMarket(marketId);
                var purchasesByName = {};

                $.each(purchases, function (key, purchase) {
                    if (purchase.name) {
                        purchasesByName[normalizeTemporaryPurchaseName(purchase.name)] = purchase;
                    }
                });

                return $.map(lines, function (line) {
                    var purchase = purchases['type:' + String(line.type_id)] || purchasesByName[normalizeTemporaryPurchaseName(line.name)];
                    var purchasedQuantity = Number(purchase ? purchase.quantity : 0);
                    var remainingQuantity = Math.max(0, Number(line.quantity || 0) - purchasedQuantity);

                    if (remainingQuantity <= 0) {
                        return null;
                    }

                    return $.extend({}, line, {
                        quantity: remainingQuantity,
                        volume: remainingQuantity * Number(line.unit_volume || 0),
                        value: remainingQuantity * Number(line.unit_value || 0),
                        line: line.name + '\t' + remainingQuantity
                    });
                });
            }

            function renderTemporaryPurchases(modal, lines) {
                var $tools = modal.find('.market-seeding-purchased-tools');
                var marketId = $tools.data('market-id');
                var purchases = purchasesForMarket(marketId);
                var byTypeId = {};
                var byName = {};
                var rows = [];
                var totalQuantity = 0;
                var totalVolume = 0;
                var totalValue = 0;

                $.each(lines, function (index, line) {
                    byTypeId[String(line.type_id)] = line;
                    byName[normalizeTemporaryPurchaseName(line.name)] = line;
                });

                $.each(purchases, function (purchaseKey, purchase) {
                    var line = byTypeId[String(purchase.type_id)] || byName[normalizeTemporaryPurchaseName(purchase.name)];
                    var quantity = Number(purchase.quantity || 0);

                    if (quantity < 0) {
                        return;
                    }

                    var unitVolume = line ? Number(line.unit_volume || 0) : Number(purchase.unit_volume || 0);
                    var unitValue = line ? Number(line.unit_value || 0) : Number(purchase.unit_value || 0);
                    totalQuantity += quantity;
                    totalVolume += quantity * unitVolume;
                    totalValue += quantity * unitValue;
                    rows.push({
                        purchase_key: purchase.purchase_key || purchaseKey,
                        type_id: Number(purchase.type_id || (line ? line.type_id : 0) || 0),
                        name: purchase.name || (line ? line.name : 'Unknown item'),
                        quantity: quantity
                    });
                });

                var $body = $tools.find('.market-seeding-purchased-table tbody');
                $body.empty();

                if (!rows.length) {
                    $body.append('<tr class="market-seeding-purchased-empty-row"><td colspan="3" class="text-muted">No temporary purchases marked for this market.</td></tr>');
                } else {
                    rows.sort(function (a, b) {
                        return a.name.localeCompare(b.name);
                    });

                    $.each(rows, function (index, row) {
                        var icon = row.type_id > 0
                            ? '<img src="' + escapeHtml(eveTypeIconUrl(row.type_id, 32)) + '" alt="">'
                            : '<i class="fas fa-cube text-muted"></i>';
                        $body.append(
                            '<tr data-purchase-key="' + escapeHtml(row.purchase_key) + '">' +
                                '<td><span class="market-seeding-purchased-item"><span class="market-seeding-purchased-item-icon">' + icon + '</span><span>' + escapeHtml(row.name) + '</span></span></td>' +
                                '<td class="text-right">' +
                                    '<input type="number" min="0" step="1" class="form-control form-control-sm market-seeding-purchased-quantity ml-auto" value="' + escapeHtml(row.quantity) + '">' +
                                '</td>' +
                                '<td class="text-right">' +
                                    '<button type="button" class="btn btn-xs btn-outline-danger market-seeding-remove-purchased">Remove</button>' +
                                '</td>' +
                            '</tr>'
                        );
                    });
                }

                $tools.find('[data-purchased-summary="items"]').text(numberWithCommas(rows.length));
                $tools.find('[data-purchased-summary="volume"]').text(formatDecimal(totalVolume));
                $tools.find('[data-purchased-summary="value"]').text(formatMetricMoney(totalValue));
            }

            function updateDashboardTemporaryPurchaseBadges() {
                $('.market-seeding-dashboard-table tbody tr').each(function () {
                    var $row = $(this);
                    var marketId = $row.data('market-id');
                    var typeId = $row.data('type-id');
                    var itemName = $row.data('item-name');
                    var missingQuantity = Number($row.data('missing-quantity') || 0);
                    var purchases = purchasesForMarket(marketId);
                    var purchase = purchases['type:' + String(typeId)] || purchases['name:' + normalizeTemporaryPurchaseName(itemName)];
                    var purchasedQuantity = Number(purchase ? purchase.quantity : 0);
                    var $itemCell = $row.find('td:first');

                    $row.toggleClass('market-seeding-locally-purchased', purchasedQuantity > 0);
                    $itemCell.find('.market-seeding-local-purchase-badge').remove();

                    if (purchasedQuantity <= 0) {
                        return;
                    }

                    var label = purchasedQuantity >= missingQuantity && missingQuantity > 0 ? 'Bought locally' : 'Partially bought';
                    $itemCell.append(
                        '<span class="badge badge-success market-seeding-local-purchase-badge" title="Stored only in this browser">' +
                            escapeHtml(label) +
                        '</span>'
                    );
                });
            }

            function parseTemporaryPurchaseImport(text, lines) {
                var byName = {};
                var purchases = {};
                var stats = {
                    rows: 0,
                    importedRows: 0,
                    ignoredSellRows: 0,
                    unparsedRows: 0
                };

                $.each(lines, function (index, line) {
                    byName[normalizeTemporaryPurchaseName(line.name)] = line;
                });

                String(text || '').split(/\r?\n/).forEach(function (rawLine) {
                    var line = $.trim(rawLine);

                    if (!line) {
                        return;
                    }

                    stats.rows++;
                    var parsed = parseTemporaryPurchaseLine(line, byName);

                    if (!parsed) {
                        stats.unparsedRows++;
                        return;
                    }

                    if (parsed.ignored_sell) {
                        stats.ignoredSellRows++;
                        return;
                    }

                    stats.importedRows++;
                    var key = parsed.purchase_key || temporaryPurchaseItemKey(parsed);
                    purchases[key] = purchases[key] || {
                        purchase_key: key,
                        type_id: parsed.type_id,
                        name: parsed.name,
                        unit_volume: parsed.unit_volume || 0,
                        unit_value: parsed.unit_value || 0,
                        quantity: 0
                    };
                    purchases[key].quantity += parsed.quantity;
                });

                var items = $.map(purchases, function (purchase) {
                    return purchase;
                });
                stats.uniqueItems = items.length;

                return {
                    items: items,
                    stats: stats
                };
            }

            function renderTemporaryPurchaseImportResult($tools, stats) {
                var $result = $tools.find('.market-seeding-purchased-import-result');
                var importedRows = Number(stats.importedRows || 0);
                var uniqueItems = Number(stats.uniqueItems || 0);
                var ignoredSellRows = Number(stats.ignoredSellRows || 0);
                var unparsedRows = Number(stats.unparsedRows || 0);
                var alertClass = importedRows > 0 ? 'alert-success' : 'alert-warning';
                var details = [];

                details.push('<strong>' + numberWithCommas(importedRows) + '</strong> row(s) imported');
                details.push('<strong>' + numberWithCommas(uniqueItems) + '</strong> unique item(s)');

                if (ignoredSellRows > 0) {
                    details.push('<strong>' + numberWithCommas(ignoredSellRows) + '</strong> sell transaction(s) ignored');
                }

                if (unparsedRows > 0) {
                    details.push('<strong>' + numberWithCommas(unparsedRows) + '</strong> row(s) could not be read');
                }

                $result
                    .removeClass('d-none alert-success alert-warning alert-danger')
                    .addClass('alert ' + alertClass + ' py-2 px-3 mb-0')
                    .html(details.join(' &middot; '));
            }

            function parseTemporaryPurchaseLine(line, byName) {
                var columns = line.split('\t');
                var itemName = '';
                var quantity = 0;
                var looksLikeMarketTransaction = columns.length >= 7 && /^\d{4}\.\d{2}\.\d{2}\s+\d{2}:\d{2}/.test($.trim(columns[0]));

                if (looksLikeMarketTransaction) {
                    if (parseMoney(columns[4]) >= 0) {
                        return {
                            ignored_sell: true
                        };
                    }

                    quantity = parseNumber(columns[1]);
                    itemName = $.trim(columns[2]);
                } else if (columns.length >= 2) {
                    itemName = $.trim(columns[0]);
                    quantity = parseNumber(columns[1]);
                } else {
                    var simpleMatch = line.match(/^(.+?)\s+x\s*([\d,]+)$/i) || line.match(/^(.+?)\s+([\d,]+)$/);

                    if (simpleMatch) {
                        itemName = $.trim(simpleMatch[1]);
                        quantity = parseNumber(simpleMatch[2]);
                    }
                }

                if (!itemName || quantity <= 0) {
                    return null;
                }

                var match = byName[normalizeTemporaryPurchaseName(itemName)];

                return {
                    purchase_key: match ? 'type:' + match.type_id : 'name:' + normalizeTemporaryPurchaseName(itemName),
                    type_id: match ? match.type_id : 0,
                    name: match ? match.name : itemName,
                    unit_volume: match ? Number(match.unit_volume || 0) : 0,
                    unit_value: match ? Number(match.unit_value || 0) : 0,
                    quantity: quantity
                };
            }

            function applyRestockLimits(lines, freightLimit, valueLimit) {
                var hasFreightLimit = freightLimit && freightLimit > 0;
                var hasValueLimit = valueLimit && valueLimit > 0;

                if (!hasFreightLimit && !hasValueLimit) {
                    return lines;
                }

                var candidates = $.map(lines, function (line, index) {
                    var quantity = Number(line.quantity || 0);
                    var unitVolume = Number(line.unit_volume || 0);
                    var unitValue = Number(line.unit_value || 0);

                    return $.extend({}, line, {
                        originalIndex: index,
                        quantity: quantity,
                        unit_volume: unitVolume,
                        unit_value: unitValue,
                        volume: quantity * unitVolume,
                        value: quantity * unitValue
                    });
                });
                var freeSelections = {};

                $.each(candidates, function (index, line) {
                    var consumesVolume = hasFreightLimit && line.unit_volume > 0;
                    var consumesValue = hasValueLimit && line.unit_value > 0;

                    if (!consumesVolume && !consumesValue && line.quantity > 0) {
                        freeSelections[line.originalIndex] = $.extend({}, line);
                    }
                });

                var limitedCandidates = $.grep(candidates, function (line) {
                    var consumesVolume = hasFreightLimit && line.unit_volume > 0;
                    var consumesValue = hasValueLimit && line.unit_value > 0;

                    return line.quantity > 0 && (consumesVolume || consumesValue);
                });
                var packingOrders = [
                    limitedCandidates.slice().sort(function (a, b) {
                        return a.originalIndex - b.originalIndex;
                    }),
                    limitedCandidates.slice().sort(function (a, b) {
                        return a.unit_volume - b.unit_volume || a.originalIndex - b.originalIndex;
                    }),
                    limitedCandidates.slice().sort(function (a, b) {
                        return a.unit_value - b.unit_value || a.originalIndex - b.originalIndex;
                    }),
                    limitedCandidates.slice().sort(function (a, b) {
                        if (b.unit_volume !== a.unit_volume) {
                            return b.unit_volume - a.unit_volume;
                        }

                        return a.originalIndex - b.originalIndex;
                    }),
                    limitedCandidates.slice().sort(function (a, b) {
                        if (b.unit_value !== a.unit_value) {
                            return b.unit_value - a.unit_value;
                        }

                        return a.originalIndex - b.originalIndex;
                    }),
                    limitedCandidates.slice().sort(function (a, b) {
                        if (b.volume !== a.volume) {
                            return b.volume - a.volume;
                        }

                        return a.originalIndex - b.originalIndex;
                    }),
                    limitedCandidates.slice().sort(function (a, b) {
                        if (b.value !== a.value) {
                            return b.value - a.value;
                        }

                        return a.originalIndex - b.originalIndex;
                    })
                ];
                var bestSelection = {
                    selectedByIndex: $.extend({}, freeSelections),
                    volume: 0,
                    value: 0,
                    score: -1
                };

                $.each(packingOrders, function (index, orderedCandidates) {
                    var packed = packRestockCandidates(orderedCandidates, freeSelections, freightLimit, valueLimit);

                    if (packed.score > bestSelection.score) {
                        bestSelection = packed;
                    }
                });

                return $.map(lines, function (line, index) {
                    return bestSelection.selectedByIndex[index] || null;
                });
            }

            function packRestockCandidates(candidates, freeSelections, freightLimit, valueLimit) {
                var selectedByIndex = $.extend({}, freeSelections);
                var remainingVolume = freightLimit && freightLimit > 0 ? freightLimit : null;
                var remainingValue = valueLimit && valueLimit > 0 ? valueLimit : null;
                var selectedVolume = 0;
                var selectedValue = 0;

                $.each(candidates, function (index, line) {
                    var maxQuantity = line.quantity;

                    if (remainingVolume !== null && line.unit_volume > 0) {
                        maxQuantity = Math.min(maxQuantity, Math.floor((remainingVolume + 0.0000001) / line.unit_volume));
                    }

                    if (remainingValue !== null && line.unit_value > 0) {
                        maxQuantity = Math.min(maxQuantity, Math.floor((remainingValue + 0.0000001) / line.unit_value));
                    }

                    if (maxQuantity <= 0) {
                        return;
                    }

                    var quantity = Math.max(0, maxQuantity);
                    var lineVolume = quantity * Number(line.unit_volume || 0);
                    var lineValue = quantity * Number(line.unit_value || 0);
                    if (remainingVolume !== null) {
                        remainingVolume -= lineVolume;
                    }
                    if (remainingValue !== null) {
                        remainingValue -= lineValue;
                    }
                    selectedVolume += lineVolume;
                    selectedValue += lineValue;
                    selectedByIndex[line.originalIndex] = $.extend({}, line, {
                        quantity: quantity,
                        volume: lineVolume,
                        value: lineValue,
                        line: line.name + '\t' + quantity
                    });
                });

                return {
                    selectedByIndex: selectedByIndex,
                    volume: selectedVolume,
                    value: selectedValue,
                    score: restockLimitScore(selectedVolume, selectedValue, freightLimit, valueLimit)
                };
            }

            function restockLimitScore(selectedVolume, selectedValue, freightLimit, valueLimit) {
                var score = 0;

                if (freightLimit && freightLimit > 0) {
                    score += Math.min(1, selectedVolume / freightLimit);
                }

                if (valueLimit && valueLimit > 0) {
                    score += Math.min(1, selectedValue / valueLimit);
                }

                return score;
            }

            function restockLineValue(line) {
                return Number(line.value || 0) || (Number(line.quantity || 0) * Number(line.unit_value || 0));
            }

            function matchesDashboardFilters(category, group, stockStatus, priority, sourceFilters, selectedCategory, selectedGroup, selectedSource, selectedStatus, selectedPriority) {
                return (!selectedCategory || category === selectedCategory)
                    && (!selectedGroup || group === selectedGroup)
                    && matchesDashboardSourceFilter(sourceFilters, selectedSource)
                    && matchesStockStatusFilter(stockStatus, selectedStatus)
                    && (!selectedPriority || priority === selectedPriority);
            }

            function matchesDashboardSourceFilter(sourceFilters, selectedSource) {
                if (!selectedSource) {
                    return true;
                }

                var sourceFilterText = $.isArray(sourceFilters)
                    ? ' ' + sourceFilters.join(' ') + ' '
                    : ' ' + String(sourceFilters || '') + ' ';

                return sourceFilterText.indexOf(' ' + selectedSource + ' ') !== -1;
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

                if (preferences.pricingMode !== undefined) {
                    $modal.find('.market-seeding-listing-helper-mode-input[value="' + preferences.pricingMode + '"]').prop('checked', true);
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

                if (preferences.smart) {
                    if (preferences.smart.ammoMarkup !== undefined) {
                        $modal.find('.market-seeding-listing-helper-smart-ammo').val(preferences.smart.ammoMarkup);
                    }
                    if (preferences.smart.shipMarkup !== undefined) {
                        $modal.find('.market-seeding-listing-helper-smart-ships').val(preferences.smart.shipMarkup);
                    }
                    if (preferences.smart.droneMarkup !== undefined) {
                        $modal.find('.market-seeding-listing-helper-smart-drones').val(preferences.smart.droneMarkup);
                    }
                    if (preferences.smart.floor !== undefined) {
                        $modal.find('.market-seeding-listing-helper-smart-floor').val(preferences.smart.floor);
                    }
                    $modal.find('.market-seeding-listing-helper-smart-floor-skip-ammo').prop('checked', preferences.smart.skipAmmoFloor !== false);

                    $.each(preferences.smart.tiers || {}, function (tier, value) {
                        $modal.find('.market-seeding-listing-helper-smart-tier[data-tier="' + tier + '"]').val(value);
                    });
                }

                formatMoneyInput($modal.find('.market-seeding-listing-helper-smart-floor'));
                updateListingHelperSmartVisibility($modal);
            }

            function saveListingHelperPreferences($modal) {
                var storage = listingHelperStorage();

                if (!storage) {
                    return;
                }

                var preferences = {
                    pricingMode: listingHelperPricingMode($modal),
                    markup: $modal.find('.market-seeding-listing-helper-markup').val(),
                    tax: $modal.find('.market-seeding-listing-helper-tax').val(),
                    broker: $modal.find('.market-seeding-listing-helper-broker').val(),
                    competitive: $modal.find('.market-seeding-listing-helper-competitive').is(':checked'),
                    excludeProblemItems: $modal.find('.market-seeding-listing-helper-exclude-problem-items').is(':checked'),
                    smart: listingHelperSmartPreferences($modal)
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

            function updateListingHelperSmartVisibility($modal) {
                var isSmart = listingHelperPricingMode($modal) === 'smart';

                $modal.find('.market-seeding-listing-helper-smart').toggleClass('d-none', !isSmart);
                $modal.find('.market-seeding-listing-helper-simple-only').toggleClass('d-none', isSmart);
            }

            function applyListingHelperSmartDefaults($modal) {
                $modal.find('.market-seeding-listing-helper-smart-ammo').val(listingHelperSmartDefaults.ammoMarkup);
                $modal.find('.market-seeding-listing-helper-smart-ships').val(listingHelperSmartDefaults.shipMarkup);
                $modal.find('.market-seeding-listing-helper-smart-drones').val(listingHelperSmartDefaults.droneMarkup);
                $modal.find('.market-seeding-listing-helper-smart-floor').val(formatWholeNumber(listingHelperSmartDefaults.floor));
                $modal.find('.market-seeding-listing-helper-smart-floor-skip-ammo').prop('checked', listingHelperSmartDefaults.skipAmmoFloor);

                $.each(listingHelperSmartDefaults.tiers, function (tier, value) {
                    $modal.find('.market-seeding-listing-helper-smart-tier[data-tier="' + tier + '"]').val(value);
                });
            }

            function formatMoneyInput($input, preserveCursor) {
                var rawValue = $.trim($input.val());

                if (rawValue === '') {
                    return;
                }

                var input = $input[0];
                var cursor = preserveCursor && input ? input.selectionStart : null;
                var digitsBeforeCursor = cursor === null
                    ? null
                    : String($input.val()).slice(0, cursor).replace(/\D/g, '').length;
                var formatted = formatWholeNumber(parseMoney(rawValue));

                $input.val(formatted);

                if (digitsBeforeCursor === null || !input || typeof input.setSelectionRange !== 'function') {
                    return;
                }

                var nextCursor = formatted.length;
                var digitsSeen = 0;

                for (var index = 0; index < formatted.length; index++) {
                    if (/\d/.test(formatted.charAt(index))) {
                        digitsSeen++;
                    }

                    if (digitsSeen >= digitsBeforeCursor) {
                        nextCursor = index + 1;
                        break;
                    }
                }

                input.setSelectionRange(nextCursor, nextCursor);
            }

            function listingHelperPricingMode($modal) {
                return $modal.find('.market-seeding-listing-helper-mode-input:checked').val() || 'simple';
            }

            function listingHelperSmartPreferences($modal) {
                var tiers = {};

                $modal.find('.market-seeding-listing-helper-smart-tier').each(function () {
                    tiers[$(this).data('tier')] = $(this).val();
                });

                return {
                    ammoMarkup: $modal.find('.market-seeding-listing-helper-smart-ammo').val(),
                    shipMarkup: $modal.find('.market-seeding-listing-helper-smart-ships').val(),
                    droneMarkup: $modal.find('.market-seeding-listing-helper-smart-drones').val(),
                    floor: formatWholeNumber(parseMoney($modal.find('.market-seeding-listing-helper-smart-floor').val())),
                    skipAmmoFloor: $modal.find('.market-seeding-listing-helper-smart-floor-skip-ammo').is(':checked'),
                    tiers: tiers
                };
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
                    transactionCount: 0,
                    quantityOnlyCount: 0
                };

                String(text || '').split(/\r?\n/).forEach(function (line) {
                    line = $.trim(line);

                    if (!line) {
                        return;
                    }

                    var columns = line.split('\t');

                    var isCharacterLog = columns.length === 7;
                    var isCorporationLog = columns.length >= 9;
                    var isQuantityOnly = columns.length === 2;

                    if (!isCharacterLog && !isCorporationLog && !isQuantityOnly) {
                        result.skipped++;
                        return;
                    }

                    if (isQuantityOnly) {
                        var quantityOnlyName = $.trim(columns[0]);
                        var quantityOnlyQuantity = parseNumber(columns[1]);

                        if (!quantityOnlyName || quantityOnlyQuantity <= 0) {
                            result.skipped++;
                            return;
                        }

                        if (!result.items[quantityOnlyName]) {
                            result.items[quantityOnlyName] = {
                                name: quantityOnlyName,
                                quantity: 0,
                                highestCost: 0,
                                transactionCount: 0,
                                quantityOnlyCount: 0
                            };
                        }

                        result.items[quantityOnlyName].quantity += quantityOnlyQuantity;
                        result.items[quantityOnlyName].quantityOnlyCount++;
                        result.quantityOnlyCount++;
                        return;
                    }

                    var quantity = parseNumber(columns[1]);
                    var itemName = $.trim(columns[2]);
                    var unitCost = parseMoney(columns[3]);
                    var totalCost = parseMoney(columns[4]);

                    if (totalCost >= 0) {
                        return;
                    }

                    if (!itemName || quantity <= 0 || unitCost <= 0) {
                        result.skipped++;
                        return;
                    }

                    if (!result.items[itemName]) {
                        result.items[itemName] = {
                            name: itemName,
                            quantity: 0,
                            highestCost: 0,
                            transactionCount: 0,
                            quantityOnlyCount: 0
                        };
                    }

                    result.items[itemName].quantity += quantity;
                    result.items[itemName].highestCost = Math.max(result.items[itemName].highestCost, unitCost);
                    result.items[itemName].transactionCount++;
                    result.transactionCount++;
                });

                return result;
            }

            function listingHelperPricingPolicy($modal) {
                var tiers = {};

                $modal.find('.market-seeding-listing-helper-smart-tier').each(function () {
                    tiers[$(this).data('tier')] = parseFloat($(this).val() || 0);
                });

                return {
                    mode: listingHelperPricingMode($modal),
                    simpleMarkup: parseFloat($modal.find('.market-seeding-listing-helper-markup').val() || 0),
                    ammoMarkup: parseFloat($modal.find('.market-seeding-listing-helper-smart-ammo').val() || 0),
                    shipMarkup: parseFloat($modal.find('.market-seeding-listing-helper-smart-ships').val() || 0),
                    droneMarkup: parseFloat($modal.find('.market-seeding-listing-helper-smart-drones').val() || 0),
                    floor: parsePositiveMoney($modal.find('.market-seeding-listing-helper-smart-floor').val()) || 0,
                    skipAmmoFloor: $modal.find('.market-seeding-listing-helper-smart-floor-skip-ammo').is(':checked'),
                    tiers: tiers
                };
            }

            function listingHelperMarkupPrice(costBasis, priceInfo, policy) {
                if (policy.mode !== 'smart') {
                    return {
                        price: roundUpToEvePrice(costBasis * (1 + (policy.simpleMarkup / 100))),
                        rule: 'Simple ' + formatCompactPercent(policy.simpleMarkup)
                    };
                }

                var category = String(priceInfo.category || '');
                var tier = listingHelperSmartTier(costBasis);
                var markup = tier.markup(policy);
                var rule = tier.label + ' ' + formatCompactPercent(markup);

                if (category === 'Ammunition & Charges') {
                    markup = policy.ammoMarkup;
                    rule = 'Ammo ' + formatCompactPercent(markup);
                } else if (category === 'Ships') {
                    markup = policy.shipMarkup;
                    rule = 'Ship ' + formatCompactPercent(markup);
                } else if (category === 'Drones') {
                    markup = policy.droneMarkup;
                    rule = 'Drone ' + formatCompactPercent(markup);
                }

                var basePrice = costBasis * (1 + (markup / 100));
                var floorApplies = policy.floor > 0 && !(policy.skipAmmoFloor && category === 'Ammunition & Charges');
                var floorPrice = floorApplies ? costBasis + policy.floor : 0;
                var floorUsed = floorPrice > basePrice;
                var price = roundUpToEvePrice(Math.max(basePrice, floorPrice));

                return {
                    price: price,
                    rule: floorUsed ? 'Min profit +' + formatMetricMoney(policy.floor) : rule
                };
            }

            function listingHelperSmartTier(costBasis) {
                if (costBasis < 25000) {
                    return {
                        label: '<25k',
                        markup: function (policy) { return policy.tiers.under25k || 0; }
                    };
                }

                if (costBasis < 1000000) {
                    return {
                        label: '25k-1m',
                        markup: function (policy) { return policy.tiers.under1m || 0; }
                    };
                }

                if (costBasis < 20000000) {
                    return {
                        label: '1m-20m',
                        markup: function (policy) { return policy.tiers.under20m || 0; }
                    };
                }

                if (costBasis < 100000000) {
                    return {
                        label: '20m-100m',
                        markup: function (policy) { return policy.tiers.under100m || 0; }
                    };
                }

                return {
                    label: '>100m',
                    markup: function (policy) { return policy.tiers.over100m || 0; }
                };
            }

            function renderListingHelper($modal, parsed, prices) {
                var pricingPolicy = listingHelperPricingPolicy($modal);
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

                if (parsed.quantityOnlyCount) {
                    warnings.push(parsed.quantityOnlyCount + ' item + quantity row(s) did not include purchase pricing. Jita pricing is being used as the original cost basis for those rows.');
                }

                $.each(parsed.items, function (itemName, item) {
                    var priceInfo = prices[itemName] || {};
                    var jitaPrice = priceInfo.jita_price ? parseFloat(priceInfo.jita_price) : null;
                    var useJitaCostBasis = item.highestCost <= 0;
                    var costBasis = useJitaCostBasis ? (jitaPrice || 0) : item.highestCost;
                    var markupResult = listingHelperMarkupPrice(costBasis, priceInfo, pricingPolicy);
                    var markupPrice = markupResult.price;
                    var localUndercutPrice = priceInfo.local_price ? previousEvePrice(parseFloat(priceInfo.local_price)) : null;
                    var competitivePrice = useCompetitive ? localUndercutPrice : null;
                    var sellPrice = competitivePrice ? Math.min(markupPrice, competitivePrice) : markupPrice;
                    var gross = sellPrice * item.quantity;
                    var basis = costBasis * item.quantity;
                    var fees = gross * feeRate;
                    var profit = gross - basis - fees;
                    var profitPercent = basis > 0 ? (profit / basis) * 100 : 0;

                    var usedCompetitive = competitivePrice && sellPrice === competitivePrice;

                    var notes = [];

                    var isUnknown = priceInfo.found === false;
                    var isBelowBreakEven = profit < 0;
                    var isMissingCostBasis = useJitaCostBasis && !jitaPrice;
                    var ownSellOrders = priceInfo.own_sell_orders || null;

                    if (isUnknown) {
                        stats.unknown++;
                        notes.push({ label: 'SDE missing', className: 'badge-danger' });
                    } else if (useCompetitive && !priceInfo.local_price) {
                        stats.noLocal++;
                        notes.push({ label: 'No local sell', className: 'badge-info' });
                    }

                    if (useJitaCostBasis) {
                        if (jitaPrice) {
                            notes.push({ label: 'Jita cost basis', className: 'badge-info' });
                        } else if (!isUnknown) {
                            notes.push({ label: 'No Jita cost basis', className: 'badge-danger' });
                        }
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

                    notes.push({
                        label: markupResult.rule,
                        className: pricingPolicy.mode === 'smart' ? 'badge-primary' : 'badge-secondary'
                    });

                    if (ownSellOrders && Number(ownSellOrders.count || 0) > 0) {
                        notes.push({ label: 'Your order exists', className: 'badge-warning' });
                    }

                    reviewRows.push({
                        name: item.name,
                        quantity: item.quantity,
                        highestCost: costBasis,
                        costBasisSource: useJitaCostBasis ? 'jita' : 'transaction',
                        sellPrice: sellPrice,
                        localPrice: priceInfo.local_price ? parseFloat(priceInfo.local_price) : null,
                        jitaPrice: jitaPrice,
                        ownSellOrders: ownSellOrders,
                        profit: profit,
                        profitPercent: profitPercent,
                        notes: notes
                    });

                    if (!excludeProblemItems || (!isUnknown && !isMissingCostBasis && !isBelowBreakEven)) {
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
                    var ownOrdersHtml = '';

                    if (row.ownSellOrders && Number(row.ownSellOrders.count || 0) > 0) {
                        var characters = (row.ownSellOrders.characters || []).join(', ');
                        var lowestPrice = row.ownSellOrders.lowest_price
                            ? ' at ' + formatMetricMoney(row.ownSellOrders.lowest_price)
                            : '';

                        ownOrdersHtml = '<div class="text-warning small">Your orders: ' +
                            numberWithCommas(row.ownSellOrders.quantity || 0) +
                            ' listed' + lowestPrice +
                            (characters ? ' by ' + escapeHtml(characters) : '') +
                            '</div>';
                    }

                    var costBasisLabel = row.costBasisSource === 'jita'
                        ? '<div class="text-muted small">from Jita</div>'
                        : '<div class="text-muted small">from transaction</div>';

                    $body.append(
                        '<tr>' +
                            '<td>' +
                                '<strong>' + escapeHtml(row.name) + '</strong>' +
                                (row.jitaPrice ? '<div class="text-muted small">Jita ' + formatMetricMoney(row.jitaPrice) + '</div>' : '') +
                                ownOrdersHtml +
                            '</td>' +
                            '<td class="text-right" data-order="' + row.quantity + '">' + numberWithCommas(row.quantity) + '</td>' +
                            '<td class="text-right" data-order="' + row.highestCost + '">' + formatMetricMoney(row.highestCost) + costBasisLabel + '</td>' +
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

            function parsePositiveMoney(value) {
                var parsed = parseMoney(value);

                return parsed > 0 ? parsed : null;
            }

            function formatEvePrice(value) {
                return Number(value || 0).toFixed(2);
            }

            function formatIsk(value) {
                return Number(value || 0).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }) + ' ISK';
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

            function formatCompactPercent(value) {
                return Number(value || 0).toLocaleString('en-US', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 2
                }) + '%';
            }

            function formatWholeNumber(value) {
                return Number(value || 0).toLocaleString('en-US', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                });
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

            function escapeHtml(value) {
                return $('<div>').text(value || '').html();
            }

            function eveTypeIconUrl(typeId, size) {
                typeId = parseInt(typeId || 0, 10);
                size = size || 64;

                return typeId > 0 ? 'https://images.evetech.net/types/' + typeId + '/icon?size=' + size : '';
            }

            @include('seat-market-seeding::partials.item-detail-modal-readonly-scripts')

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
