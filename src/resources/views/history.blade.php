@extends('web::layouts.grids.12')

@section('title', 'Market Seeding History')
@section('page_header', 'Market Seeding History')

@section('content')
    @php
        $activeSkin = setting('skin') ?: 'default';
        $marketSeedingThemeClass = in_array($activeSkin, ['jet', 'iuligigi', 'gigigraphite'], true)
            ? 'market-seeding-dark-skin'
            : '';
        $canManageMarketSeeding = auth()->user()->can('seat-market-seeding.manager');
        $whole = function ($value) {
            return number_format((float) $value, 0, '.', ',');
        };
        $isk = function ($value) {
            return number_format((float) $value, 2, '.', ',') . ' ISK';
        };
        $signedIsk = function ($value) {
            $value = (float) $value;

            if ($value === 0.0) {
                return '0.00 ISK';
            }

            return ($value > 0 ? '+' : '-') . number_format(abs($value), 2, '.', ',') . ' ISK';
        };
        $statusBadge = function ($status) {
            return [
                'stocked' => 'badge-success',
                'low' => 'badge-warning',
                'empty' => 'badge-danger',
            ][$status] ?? 'badge-secondary';
        };
        $recommendationDataAttributes = function ($item) {
            return [
                'data-recommended-quantity' => (int) $item->recommended_quantity,
                'data-recommendation-reason' => $item->recommendation_reason,
                'data-recommendation-estimated-sold' => (int) ($item->recommendation_estimated_sold ?? 0),
                'data-recommendation-days-with-data' => (int) ($item->recommendation_sales_days_with_data ?? 0),
                'data-recommendation-daily-sold' => (float) ($item->recommendation_daily_sold ?? 0),
                'data-recommendation-sales-window' => (int) ($item->recommendation_sales_window ?? 0),
                'data-recommendation-buffer-multiplier' => (float) ($item->recommendation_buffer_multiplier ?? 1),
                'data-recommendation-sales-target' => (int) ($item->recommendation_sales_target ?? $item->recommended_quantity),
                'data-recommendation-existing-target-covers' => !empty($item->recommendation_existing_target_covers) ? 1 : 0,
            ];
        };
        $historyCsrfToken = csrf_token();
    @endphp

    <style>
        .market-seeding-history-shell .card-header {
            align-items: center;
            display: flex;
            gap: 1rem;
            justify-content: space-between;
        }
        .market-seeding-history-shell .card-title {
            float: none;
        }
        .market-seeding-history-shell .history-filters {
            align-items: flex-end;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            display: grid;
            gap: .75rem;
            grid-template-columns: minmax(220px, 1.4fr) repeat(3, minmax(160px, 1fr)) auto;
            margin-bottom: 1rem;
            padding: .85rem;
        }
        .market-seeding-history-shell .history-filter-field label {
            color: #6c757d;
            display: block;
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .03em;
            margin-bottom: .25rem;
            text-transform: uppercase;
        }
        .market-seeding-history-shell .history-filter-actions {
            display: flex;
            gap: .5rem;
            justify-content: flex-end;
            white-space: nowrap;
        }
        .market-seeding-history-shell .history-stat-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            margin-bottom: 1rem;
        }
        .market-seeding-history-shell .history-stat {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
        }
        .market-seeding-history-shell .history-stat-label {
            color: #6c757d;
            font-size: .8rem;
            font-weight: 600;
            letter-spacing: .03em;
            text-transform: uppercase;
        }
        .market-seeding-history-shell .history-stat-value {
            font-size: 1.6rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .market-seeding-history-shell .history-stat-help {
            color: #6c757d;
            font-size: .8rem;
        }
        .market-seeding-history-shell .history-chart-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr);
            margin-bottom: 1rem;
        }
        .market-seeding-history-shell .history-transition-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: minmax(280px, .85fr) minmax(0, 2.15fr);
            margin-bottom: 1rem;
        }
        .market-seeding-history-chart {
            height: 300px;
            position: relative;
        }
        .market-seeding-history-chart-sm {
            height: 300px;
            position: relative;
        }
        .market-seeding-restock-leaders {
            margin-bottom: 1rem;
        }
        .market-seeding-restock-leaders .table {
            margin-bottom: 0;
        }
        .market-seeding-history-shell .history-item-action {
            vertical-align: text-bottom;
        }
        .market-seeding-history-shell .history-actions-column {
            width: 42px;
        }
        .market-seeding-history-shell .market-seeding-source-icons {
            display: inline-flex;
            gap: .25rem;
            margin-right: .35rem;
            vertical-align: middle;
        }
        .market-seeding-history-shell .market-seeding-source-icon {
            align-items: center;
            border-radius: 999px;
            display: inline-flex;
            font-size: .72rem;
            height: 1.35rem;
            justify-content: center;
            width: 1.35rem;
        }
        .market-seeding-history-shell .market-seeding-source-manual {
            background: rgba(0, 123, 255, .14);
            color: #0056b3;
        }
        .market-seeding-history-shell .market-seeding-source-doctrine {
            background: rgba(40, 167, 69, .16);
            color: #1e7e34;
        }
        .market-seeding-history-shell .market-seeding-item-type {
            display: block;
            margin-left: 1.85rem;
        }
        .market-seeding-history-shell .history-recommendation-config {
            color: #31505c;
        }
        .market-seeding-edit-target-modal .history-sparkline {
            display: block;
            height: 54px;
            margin-top: .5rem;
            width: 100%;
        }
        .market-seeding-edit-target-modal .history-sparkline polyline {
            fill: none;
            stroke: #17a2b8;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-width: 2.5;
        }
        .market-seeding-edit-target-modal .edit-target-trend-panel {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 1rem;
            padding: .75rem .9rem;
        }
        .market-seeding-edit-target-modal .edit-target-trend-header {
            align-items: baseline;
            display: flex;
            gap: .75rem;
            justify-content: space-between;
        }
        .market-seeding-edit-target-modal .edit-target-trend-title {
            font-size: .8rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .market-seeding-edit-target-modal .edit-target-trend-summary {
            color: #6c757d;
            font-size: .8rem;
        }
        .market-seeding-edit-target-modal .edit-target-trend-chart {
            height: 180px;
            margin-top: .75rem;
            position: relative;
        }
        .market-seeding-history-shell .history-heatmap {
            border-collapse: separate;
            border-spacing: 4px;
            min-width: 720px;
            width: 100%;
        }
        .market-seeding-history-shell .history-heatmap th {
            font-size: .75rem;
            vertical-align: bottom;
        }
        .market-seeding-history-shell .history-heatmap-cell {
            border-radius: 6px;
            min-width: 90px;
            padding: .45rem .55rem;
        }
        .market-seeding-history-shell .history-heatmap-value {
            display: block;
            font-weight: 800;
            line-height: 1.1;
        }
        .market-seeding-history-shell .history-heatmap-sub {
            display: block;
            font-size: .72rem;
            opacity: .8;
        }
        .market-seeding-edit-target-modal .edit-target-delta {
            color: #6c757d;
            display: block;
            font-size: .75rem;
            font-weight: 700;
            margin-top: .15rem;
        }
        .market-seeding-edit-target-modal .edit-target-delta.is-positive {
            color: #dc3545;
        }
        .market-seeding-edit-target-modal .edit-target-delta.is-negative {
            color: #28a745;
        }
        .market-seeding-history-shell .history-restock-card .card-body {
            padding: .85rem;
        }
        .market-seeding-history-shell .history-restock-card .dataTables_wrapper .row:first-child,
        .market-seeding-history-shell .history-restock-card .dataTables_wrapper .row:last-child {
            margin-left: 0;
            margin-right: 0;
        }
        .market-seeding-history-shell .history-restock-card .table th,
        .market-seeding-history-shell .history-restock-card .table td {
            vertical-align: middle;
        }
        .market-seeding-history-shell .history-restock-card .table th.text-right,
        .market-seeding-history-shell .history-restock-card .table td.text-right {
            white-space: nowrap;
        }
        .market-seeding-history-shell .modal-history-table {
            max-height: 260px;
            overflow-y: auto;
        }
        .market-seeding-edit-target-modal .modal-dialog {
            max-width: 1060px;
        }
        .market-seeding-edit-target-modal .modal-content {
            border: 0;
            border-radius: 8px;
            overflow: hidden;
        }
        .market-seeding-edit-target-modal .modal-header,
        .market-seeding-edit-target-modal .modal-footer {
            border-color: rgba(0, 0, 0, .08);
        }
        .market-seeding-edit-target-modal .edit-target-hero {
            align-items: stretch;
            background: linear-gradient(135deg, #f8fbfd 0%, #edf4f8 100%);
            border: 1px solid #d9e5ec;
            border-radius: 8px;
            display: grid;
            gap: .85rem;
            grid-template-columns: minmax(0, 1fr) minmax(180px, auto);
            margin-bottom: 1rem;
            padding: 1rem;
        }
        .market-seeding-edit-target-modal .edit-target-hero-main {
            align-items: center;
            display: flex;
            gap: .85rem;
            min-width: 0;
        }
        .market-seeding-edit-target-modal .edit-target-type-icon,
        .market-seeding-edit-target-modal .edit-target-ship-icon {
            background: #111820;
            border: 1px solid rgba(0, 0, 0, .16);
            border-radius: 10px;
            box-shadow: 0 8px 18px rgba(15, 35, 52, .16);
            flex: 0 0 auto;
            object-fit: cover;
        }
        .market-seeding-edit-target-modal .edit-target-type-icon {
            height: 56px;
            width: 56px;
        }
        .market-seeding-edit-target-modal .edit-target-ship-icon {
            height: 42px;
            width: 42px;
        }
        .market-seeding-edit-target-modal .edit-target-item-name {
            display: block;
            font-size: 1.25rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .market-seeding-edit-target-modal .edit-target-market-name {
            color: #607d8b;
            display: block;
            font-size: .9rem;
            margin-top: .25rem;
        }
        .market-seeding-edit-target-modal .edit-target-restock-callout {
            align-items: flex-end;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: right;
        }
        .market-seeding-edit-target-modal .edit-target-restock-label {
            color: #607d8b;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .market-seeding-edit-target-modal .edit-target-restock-value {
            color: #dc3545;
            font-size: 1.55rem;
            font-weight: 800;
            line-height: 1.15;
        }
        .market-seeding-edit-target-modal .edit-target-detail-grid {
            display: grid;
            gap: .75rem;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            margin-bottom: 1rem;
        }
        .market-seeding-edit-target-modal .edit-target-detail {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            min-height: 82px;
            padding: .7rem .8rem;
        }
        .market-seeding-edit-target-modal .edit-target-detail-label {
            color: #6c757d;
            display: block;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .03em;
            text-transform: uppercase;
        }
        .market-seeding-edit-target-modal .edit-target-detail-value {
            display: block;
            font-size: 1.08rem;
            font-weight: 700;
            line-height: 1.25;
            margin-top: .15rem;
        }
        .market-seeding-edit-target-modal .edit-target-detail-note {
            color: #6c757d;
            display: block;
            font-size: .75rem;
            margin-top: .1rem;
        }
        .market-seeding-edit-target-modal .edit-target-source-panel {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 1rem;
            padding: .9rem 1rem;
        }
        .market-seeding-edit-target-modal .edit-target-source-header {
            align-items: center;
            display: flex;
            justify-content: space-between;
            margin-bottom: .65rem;
        }
        .market-seeding-edit-target-modal .edit-target-source-title {
            font-size: .8rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .market-seeding-edit-target-modal .edit-target-source-badges {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
        }
        .market-seeding-edit-target-modal .edit-target-source-list {
            display: grid;
            gap: .5rem;
        }
        .market-seeding-edit-target-modal .edit-target-source-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: .65rem .75rem;
        }
        .market-seeding-edit-target-modal .edit-target-source-name {
            font-weight: 700;
        }
        .market-seeding-edit-target-modal .edit-target-source-meta,
        .market-seeding-edit-target-modal .edit-target-source-fit-meta {
            color: #6c757d;
            font-size: .78rem;
        }
        .market-seeding-edit-target-modal .edit-target-source-fit {
            align-items: flex-start;
            border-top: 1px solid #dee2e6;
            display: flex;
            gap: .65rem;
            margin-top: .5rem;
            padding-top: .5rem;
        }
        .market-seeding-edit-target-modal .edit-target-source-fit-body {
            min-width: 0;
        }
        .market-seeding-edit-target-modal .edit-target-source-fit-name {
            font-weight: 700;
        }
        .market-seeding-edit-target-modal .edit-target-source-contribution {
            display: inline-block;
            margin-right: .5rem;
            white-space: nowrap;
        }
        .market-seeding-edit-target-modal .edit-target-workspace {
            display: grid;
            gap: 1rem;
            grid-template-columns: minmax(280px, .9fr) minmax(0, 1.35fr);
        }
        .market-seeding-edit-target-modal.is-read-only .edit-target-workspace {
            grid-template-columns: minmax(520px, 1.35fr) minmax(320px, .9fr);
        }
        .market-seeding-edit-target-modal .edit-target-panel {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            min-width: 0;
            padding: 1rem;
        }
        .market-seeding-edit-target-modal .edit-target-panel-title {
            font-size: .8rem;
            font-weight: 800;
            letter-spacing: .04em;
            margin-bottom: .75rem;
            text-transform: uppercase;
        }
        .market-seeding-edit-target-modal .edit-target-form-grid {
            display: grid;
            gap: .75rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .market-seeding-edit-target-modal #market-seeding-edit-target-recommendation {
            background: linear-gradient(135deg, #f8fbfd 0%, #edf4f8 100%);
            border: 1px solid #d9e5ec;
            color: #183247;
            border-radius: 8px;
            margin-bottom: .85rem;
            padding: .75rem .85rem;
        }
        .market-seeding-edit-target-modal .edit-target-recommendation-top {
            align-items: center;
            display: flex;
            justify-content: space-between;
            gap: .75rem;
        }
        .market-seeding-edit-target-modal .edit-target-recommendation-label {
            color: #607d8b;
            font-size: .78rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .market-seeding-edit-target-modal .edit-target-recommendation-value {
            color: #183247;
            display: inline-block;
            font-size: 1rem;
            font-weight: 800;
            line-height: 1.15;
            margin-left: .35rem;
        }
        .market-seeding-edit-target-modal .edit-target-recommendation-math {
            background: rgba(24, 50, 71, .07);
            border: 1px solid rgba(24, 50, 71, .12);
            border-radius: 6px;
            font-family: Menlo, Monaco, Consolas, "Liberation Mono", monospace;
            font-size: .76rem;
            line-height: 1.45;
            margin-top: .55rem;
            padding: .45rem .55rem;
        }
        .market-seeding-edit-target-modal .edit-target-recommendation-result {
            font-size: .78rem;
            font-weight: 700;
            margin-top: .45rem;
        }
        .market-seeding-dark-skin .card,
        .market-seeding-dark-skin .card-header,
        .market-seeding-dark-skin .card-body {
            background: #222d32;
            border-color: #3c4b54;
            color: #e9ecef;
        }
        .market-seeding-dark-skin .text-muted {
            color: #b8c7ce !important;
        }
        .market-seeding-dark-skin .history-filters {
            background: #1f292e;
            border-color: #3c4b54;
        }
        .market-seeding-dark-skin .history-filter-field label {
            color: #b8c7ce;
        }
        .market-seeding-dark-skin .history-filters .form-control,
        .market-seeding-dark-skin .history-filters .form-control option {
            background: #1f2d3d;
            border-color: #3c4b54;
            color: #e9ecef;
        }
        .market-seeding-dark-skin .history-stat {
            background: #1f292e;
            border-color: #3c4b54;
        }
        .market-seeding-dark-skin .history-stat-label,
        .market-seeding-dark-skin .history-stat-help {
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
        .market-seeding-dark-skin .history-recommendation-config {
            color: #d7eef8;
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin #market-seeding-edit-target-recommendation {
            background: linear-gradient(135deg, #22313a 0%, #1b272e 100%);
            border-color: #3c4b54;
            color: #f4e7be;
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-recommendation-label {
            color: #b8c7ce;
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-recommendation-value {
            color: #f4e7be;
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-recommendation-math {
            background: rgba(31, 41, 46, .6);
            border-color: rgba(184, 199, 206, .25);
            color: #f4e7be;
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-recommendation-result {
            color: #d7eef8;
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin .history-sparkline polyline {
            stroke: #7bdff2;
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-trend-panel {
            background: #1f292e;
            border-color: #3c4b54;
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-source-panel,
        .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-source-card,
        .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-source-fit {
            background: #1f292e;
            border-color: #3c4b54;
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-source-meta,
        .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-source-fit-meta {
            color: #b8c7ce;
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-trend-summary {
            color: #b8c7ce;
        }
        .market-seeding-dark-skin .history-heatmap-cell {
            color: #f4e7be;
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-delta {
            color: #b8c7ce;
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-delta.is-positive {
            color: #ffb3bc;
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-delta.is-negative {
            color: #a9e7bd;
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-type-icon,
        .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-ship-icon {
            border-color: rgba(244, 231, 190, .18);
            box-shadow: 0 8px 18px rgba(0, 0, 0, .35);
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin .modal-content {
            background: #2f2927;
            color: #f4e7be;
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin .modal-header,
        .market-seeding-edit-target-modal.market-seeding-dark-skin .modal-footer {
            border-color: rgba(244, 231, 190, .24);
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin .close {
            color: #f4e7be;
            opacity: .75;
            text-shadow: none;
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-hero {
            background: linear-gradient(135deg, #3b3330 0%, #292523 100%);
            border-color: rgba(244, 231, 190, .22);
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-market-name,
        .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-restock-label {
            color: #b9a998;
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-restock-value {
            color: #ff9aa7;
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-detail,
        .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-panel {
            background: #1f292e;
            border-color: #3c4b54;
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-detail-label,
        .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-detail-note {
            color: #b8c7ce;
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin .form-control {
            background: #756f6c;
            border-color: #756f6c;
            color: #f4e7be;
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin .table {
            color: #f4e7be;
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin .table thead th,
        .market-seeding-edit-target-modal.market-seeding-dark-skin .table td {
            border-color: rgba(244, 231, 190, .24);
        }
        .market-seeding-dark-skin .table {
            color: #e9ecef;
        }
        .market-seeding-dark-skin .table thead th,
        .market-seeding-dark-skin .table td {
            border-color: #3c4b54;
        }
        @media (max-width: 991.98px) {
            .market-seeding-history-shell .history-chart-grid,
            .market-seeding-history-shell .history-transition-grid {
                grid-template-columns: 1fr;
            }
            .market-seeding-edit-target-modal .edit-target-detail-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .market-seeding-edit-target-modal .edit-target-workspace {
                grid-template-columns: 1fr;
            }
            .market-seeding-history-shell .history-filters {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .market-seeding-history-shell .history-filter-actions {
                justify-content: flex-start;
            }
        }
        @media (max-width: 575.98px) {
            .market-seeding-edit-target-modal .edit-target-hero,
            .market-seeding-edit-target-modal .edit-target-detail-grid,
            .market-seeding-edit-target-modal .edit-target-form-grid {
                grid-template-columns: 1fr;
            }
            .market-seeding-edit-target-modal .edit-target-restock-callout {
                align-items: flex-start;
                text-align: left;
            }
            .market-seeding-history-shell .history-filters {
                grid-template-columns: 1fr;
            }
            .market-seeding-history-shell .history-filter-actions {
                flex-wrap: wrap;
            }
        }
    </style>

    <div class="market-seeding-history-shell {{ $marketSeedingThemeClass }}">
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title mb-0">Market History</h3>
                    <small class="text-muted">Estimated sales, restocks, and stock status transitions recorded during ESI refreshes.</small>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('market-seeding.history') }}" class="history-filters">
                    <div class="history-filter-field">
                        <label for="history-filter-market">Market</label>
                        <select name="market_id" id="history-filter-market" class="form-control">
                            <option value="">All Markets</option>
                            @foreach($markets as $market)
                                <option value="{{ $market->id }}" {{ request('market_id') == $market->id ? 'selected' : '' }}>
                                    {{ $market->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="history-filter-field">
                        <label for="history-filter-days">Time Range</label>
                        <select name="days" id="history-filter-days" class="form-control">
                            @foreach([7, 30, 60, 90, 180, 365] as $dayOption)
                                <option value="{{ $dayOption }}" {{ $days === $dayOption ? 'selected' : '' }}>
                                    Last {{ $dayOption }} days
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="history-filter-field">
                        <label for="history-filter-status">Transitions</label>
                        <select name="status" id="history-filter-status" class="form-control">
                            <option value="">All Statuses</option>
                            <option value="low" {{ request('status') === 'low' ? 'selected' : '' }}>Low</option>
                            <option value="empty" {{ request('status') === 'empty' ? 'selected' : '' }}>Empty</option>
                            <option value="stocked" {{ request('status') === 'stocked' ? 'selected' : '' }}>Recovered / Stocked</option>
                        </select>
                    </div>
                    <div class="history-filter-field">
                        <label for="history-filter-category">Category</label>
                        <select name="type_category" id="history-filter-category" class="form-control">
                            <option value="">All Categories</option>
                            @foreach($typeCategories as $typeCategory)
                                <option value="{{ $typeCategory }}" {{ request('type_category') === $typeCategory ? 'selected' : '' }}>
                                    {{ $typeCategory }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="history-filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                        <a href="{{ route('market-seeding.history') }}" class="btn btn-default">Reset</a>
                    </div>
                </form>

                <div class="history-stat-grid">
                    <div class="history-stat">
                        <div class="history-stat-label">Estimated Sold</div>
                        <div class="history-stat-value">{{ $whole($salesSummary['estimated_sold']) }}</div>
                        <div class="history-stat-help">Net quantity decreases during refreshes.</div>
                    </div>
                    <div class="history-stat">
                        <div class="history-stat-label">Average Daily Sold Value</div>
                        <div class="history-stat-value">{{ $isk($globalMetrics['average_daily_sold_value']) }}</div>
                        <div class="history-stat-help">
                            Estimated ISK purchased from the market per day across {{ $historyCoverageDays }} day{{ $historyCoverageDays === 1 ? '' : 's' }} with data
                            @if($historyCoverageDays < $days)
                                in the selected {{ $days }} day window
                            @endif
                            .
                        </div>
                    </div>
                    <div class="history-stat">
                        <div class="history-stat-label">Restocked</div>
                        <div class="history-stat-value">{{ $whole($salesSummary['restocked']) }}</div>
                        <div class="history-stat-help">Net quantity increases during refreshes.</div>
                    </div>
                    <div class="history-stat">
                        <div class="history-stat-label">Tracked Lines Seen</div>
                        <div class="history-stat-value">{{ $whole($salesSummary['tracked_lines']) }}</div>
                        <div class="history-stat-help">{{ $whole($salesSummary['sales_events']) }} refresh deltas included sales.</div>
                    </div>
                </div>

                <div class="history-stat-grid">
                    <div class="history-stat">
                        <div class="history-stat-label">Estimated Sold Value</div>
                        <div class="history-stat-value">{{ $isk($globalMetrics['sold_value']) }}</div>
                        <div class="history-stat-help">Estimated sold quantity across {{ $historyCoverageDays }} day{{ $historyCoverageDays === 1 ? '' : 's' }} with data, valued at Jita or fallback prices.</div>
                    </div>
                    <div class="history-stat">
                        <div class="history-stat-label">Restocked Value</div>
                        <div class="history-stat-value">{{ $isk($globalMetrics['restocked_value']) }}</div>
                        <div class="history-stat-help">Estimated restocked quantity across the same data window.</div>
                    </div>
                    <div class="history-stat">
                        <div class="history-stat-label">Net Value Movement</div>
                        <div class="history-stat-value">{{ $signedIsk($globalMetrics['net_value']) }}</div>
                        <div class="history-stat-help">Sold value minus restocked value for the days with data.</div>
                    </div>
                    <div class="history-stat">
                        <div class="history-stat-label">Low / Empty Pressure</div>
                        <div class="history-stat-value">{{ $whole($globalMetrics['restock_events']) }}</div>
                        <div class="history-stat-help">{{ $whole($globalMetrics['total_shortage']) }} units short across low or empty events.</div>
                    </div>
                </div>

                <div class="alert alert-info">
                    Sold quantities are estimated from changes in available sell-order quantity between ESI refreshes. They are great for seeding trends, but can include delisted or expired orders.
                </div>

                <div class="history-chart-grid">
                    <div class="card mb-0">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title mb-0">Estimated Sales Over Time</h3>
                                <small class="text-muted">Sold versus restocked quantities by refresh day.</small>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="market-seeding-history-chart">
                                <canvas id="market-seeding-sales-chart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-0">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title mb-0">Sold By Category</h3>
                            <small class="text-muted">Top categories across {{ $historyCoverageDays }} day{{ $historyCoverageDays === 1 ? '' : 's' }} with data.</small>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="market-seeding-history-chart-sm">
                                <canvas id="market-seeding-category-chart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card market-seeding-restock-leaders">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title mb-0">Market / Category Heatmap</h3>
                            <small class="text-muted">Darker cells mean more sold plus restocked movement across {{ $historyCoverageDays }} day{{ $historyCoverageDays === 1 ? '' : 's' }} with data.</small>
                        </div>
                    </div>
                    <div class="card-body">
                        @if(count($heatmapData['markets']) && count($heatmapData['categories']))
                            <div class="table-responsive">
                                <table class="history-heatmap">
                                    <thead>
                                        <tr>
                                            <th>Market</th>
                                            @foreach($heatmapData['categories'] as $category)
                                                <th>{{ $category }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($heatmapData['markets'] as $market)
                                            <tr>
                                                <th>{{ $market['name'] }}</th>
                                                @foreach($heatmapData['categories'] as $category)
                                                    @php
                                                        $cell = $market['categories'][$category];
                                                        $alpha = .08 + ($cell['intensity'] * .72);
                                                    @endphp
                                                    <td>
                                                        <div class="history-heatmap-cell" style="background: rgba(220, 53, 69, {{ $alpha }});">
                                                            <span class="history-heatmap-value">{{ $whole($cell['movement']) }}</span>
                                                            <span class="history-heatmap-sub">{{ $whole($cell['sold']) }} sold / {{ $whole($cell['restocked']) }} restocked</span>
                                                        </div>
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-muted mb-0">No market/category movement has been recorded for the current filters yet.</p>
                        @endif
                    </div>
                </div>

                <div class="card market-seeding-restock-leaders">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title mb-0">Most Sold Items</h3>
                            <small class="text-muted">Items with the highest estimated movement. Good candidates for higher targets or tighter monitoring.</small>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover market-seeding-top-sold-table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Market</th>
                                        <th class="text-right">Estimated Sold</th>
                                        <th class="text-right">Listed Now</th>
                                        <th class="text-right">Avg / Day</th>
                                        <th class="text-right">Restocked</th>
                                        <th class="text-right">Sales Events</th>
                                        <th>Last Sold</th>
                                        <th class="text-right history-actions-column">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($topSoldItems as $item)
                                        <tr>
                                            <td>
                                                @include('seat-market-seeding::partials.source-icons', ['sourceFlags' => $item->source_flags ?? []])
                                                {{ $item->type_name }}
                                                <span class="text-muted small market-seeding-item-type">{{ $item->type_category }}</span>
                                            </td>
                                            <td>
                                                {{ $item->market_name }}
                                                <div class="text-muted small">{{ $item->location_name }}</div>
                                            </td>
                                            <td class="text-right" data-order="{{ $item->estimated_sold }}">{{ $whole($item->estimated_sold) }}</td>
                                            <td class="text-right" data-order="{{ $item->latest_seen_quantity }}">{{ $whole($item->latest_seen_quantity) }}</td>
                                            <td class="text-right" data-order="{{ $historyCoverageDays ? $item->estimated_sold / $historyCoverageDays : 0 }}">{{ number_format($historyCoverageDays ? $item->estimated_sold / $historyCoverageDays : 0, 1, '.', ',') }}</td>
                                            <td class="text-right" data-order="{{ $item->restocked }}">{{ $whole($item->restocked) }}</td>
                                            <td class="text-right" data-order="{{ $item->sales_events }}">{{ $whole($item->sales_events) }}</td>
                                            <td data-order="{{ $item->last_sold_at ? \Carbon\Carbon::parse($item->last_sold_at)->timestamp : 0 }}">
                                                {{ $item->last_sold_at ? \Carbon\Carbon::parse($item->last_sold_at)->format('Y-m-d H:i') : '-' }}
                                            </td>
                                            <td class="text-right">
                                                @if($item->item_id)
                                                    <button type="button"
                                                            class="btn btn-link btn-xs p-0 history-item-action market-seeding-edit-target"
                                                            title="{{ $canManageMarketSeeding ? 'Edit target stock' : 'View item details' }}"
                                                            @if($canManageMarketSeeding) data-update-url="{{ route('market-seeding.items.update', $item->item_id) }}" @endif
                                                            data-item-name="{{ $item->type_name }}"
                                                            data-market-name="{{ $item->market_name }}"
                                                            data-history-url="{{ route('market-seeding.items.history', ['item' => $item->item_id, 'days' => $days]) }}"
                                                            data-desired-quantity="{{ (int) $item->target_quantity }}"
                                                            data-warning-quantity="{{ (int) $item->warning_quantity }}"
                                                            @foreach($recommendationDataAttributes($item) as $attribute => $value) {{ $attribute }}="{{ $value }}" @endforeach>
                                                        <i class="fas {{ $canManageMarketSeeding ? 'fa-edit' : 'fa-eye' }}"></i>
                                                    </button>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="text-muted">No estimated sales have been recorded for this filter yet.</td>
                                            <td class="text-muted">Run a couple of refreshes and this will start to fill in.</td>
                                            <td class="text-right" data-order="0">0</td>
                                            <td class="text-right" data-order="0">0</td>
                                            <td class="text-right" data-order="0">0.0</td>
                                            <td class="text-right" data-order="0">0</td>
                                            <td class="text-right" data-order="0">0</td>
                                            <td data-order="0">-</td>
                                            <td class="text-right">-</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="history-transition-grid">
                    <div class="card mb-0">
                        <div class="card-header">
                            <div>
                                <h3 class="card-title mb-0">Stock Transitions</h3>
                                <small class="text-muted">Low, empty, and recovered status changes across {{ $historyCoverageDays }} day{{ $historyCoverageDays === 1 ? '' : 's' }} with data.</small>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="market-seeding-history-chart">
                                <canvas id="market-seeding-history-chart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="card market-seeding-restock-leaders history-restock-card mb-0">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title mb-0">Most Frequent Restock Needs</h3>
                            <small class="text-muted">Items that most often moved into low or empty status{{ request('market_id') ? ' for the selected market' : '' }}.</small>
                            <small class="text-muted d-block">Restock Pace is the average time between low or empty restock-needed events across the days with data. Lower is busier.</small>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover market-seeding-restock-needs-table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Market</th>
                                        <th class="text-right">Events</th>
                                        <th class="text-right">Empty</th>
                                        <th class="text-right">Low</th>
                                        <th class="text-right">Shortage</th>
                                        <th class="text-right" title="Average time between low or empty restock-needed events across the days with data in the selected history window. Lower values mean the item needs attention more often.">Restock Pace</th>
                                        <th>Last Needed</th>
                                        <th class="text-right history-actions-column">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($restockLeaders as $leader)
                                        <tr>
                                            <td>
                                                @include('seat-market-seeding::partials.source-icons', ['sourceFlags' => $leader->source_flags ?? []])
                                                {{ $leader->type_name }}
                                                <span class="text-muted small market-seeding-item-type">{{ $leader->type_category }}</span>
                                            </td>
                                            <td>
                                                {{ $leader->market_name }}
                                                <div class="text-muted small">{{ $leader->location_name }}</div>
                                            </td>
                                            <td class="text-right" data-order="{{ $leader->restock_events }}">{{ $whole($leader->restock_events) }}</td>
                                            <td class="text-right" data-order="{{ $leader->empty_events }}">
                                                <span class="badge badge-danger">{{ $whole($leader->empty_events) }}</span>
                                            </td>
                                            <td class="text-right" data-order="{{ $leader->low_events }}">
                                                <span class="badge badge-warning">{{ $whole($leader->low_events) }}</span>
                                            </td>
                                            <td class="text-right" data-order="{{ $leader->total_shortage }}">{{ $whole($leader->total_shortage) }}</td>
                                            <td class="text-right" data-order="{{ $leader->average_days_between_restock_needs ?? 999999 }}" title="Average time between low or empty restock-needed events across {{ $historyCoverageDays }} day{{ $historyCoverageDays === 1 ? '' : 's' }} with data.">
                                                {{ $leader->average_days_between_restock_needs ? 'Every ' . number_format($leader->average_days_between_restock_needs, 1, '.', ',') . ' days' : '-' }}
                                            </td>
                                            <td data-order="{{ $leader->last_needed_at ? \Carbon\Carbon::parse($leader->last_needed_at)->timestamp : 0 }}">
                                                {{ $leader->last_needed_at ? \Carbon\Carbon::parse($leader->last_needed_at)->format('Y-m-d H:i') : '-' }}
                                            </td>
                                            <td class="text-right">
                                                @if($leader->item_id)
                                                    <button type="button"
                                                            class="btn btn-link btn-xs p-0 history-item-action market-seeding-edit-target"
                                                            title="{{ $canManageMarketSeeding ? 'Edit target stock' : 'View item details' }}"
                                                            @if($canManageMarketSeeding) data-update-url="{{ route('market-seeding.items.update', $leader->item_id) }}" @endif
                                                            data-item-name="{{ $leader->type_name }}"
                                                            data-market-name="{{ $leader->market_name }}"
                                                            data-history-url="{{ route('market-seeding.items.history', ['item' => $leader->item_id, 'days' => $days]) }}"
                                                            data-desired-quantity="{{ (int) $leader->desired_quantity }}"
                                                            data-warning-quantity="{{ (int) $leader->warning_quantity }}"
                                                            @foreach($recommendationDataAttributes($leader) as $attribute => $value) {{ $attribute }}="{{ $value }}" @endforeach>
                                                        <i class="fas {{ $canManageMarketSeeding ? 'fa-edit' : 'fa-eye' }}"></i>
                                                    </button>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="text-muted">No low or empty restock events have been recorded yet.</td>
                                            <td class="text-muted">-</td>
                                            <td class="text-right" data-order="0">0</td>
                                            <td class="text-right" data-order="0"><span class="badge badge-danger">0</span></td>
                                            <td class="text-right" data-order="0"><span class="badge badge-warning">0</span></td>
                                            <td class="text-right" data-order="0">0</td>
                                            <td class="text-right" data-order="999999" title="Average time between low or empty restock-needed events across the days with data in the selected history window.">-</td>
                                            <td data-order="0">-</td>
                                            <td class="text-right">-</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                </div>

                <div class="table-responsive">
	                    <table class="table table-sm table-hover market-seeding-history-table" data-ajax-url="{{ $historyAjaxUrl }}">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Market</th>
                                <th>Item</th>
                                <th>Status</th>
                                <th class="text-right">Current Stock</th>
                                <th class="text-right">Warning</th>
                                <th class="text-right">Target</th>
                                <th class="text-right history-actions-column">Actions</th>
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
        'canManageMarketSeeding' => $canManageMarketSeeding,
    ])
@endsection

@push('javascript')
    <script>
        $(function () {
            var chartData = @json($chartData);
            var salesChartData = @json($salesChartData);
            var categorySales = @json($categorySales);
            var selectedDays = @json($days);
            var historyCoverageDays = @json($historyCoverageDays);
            var csrfToken = @json($historyCsrfToken);
            var canManageMarketSeeding = @json($canManageMarketSeeding);
            var currentTargetDetails = {};
            var targetTrendChart = null;
            var categoryColors = [
                'rgba(0, 123, 255, .8)',
                'rgba(40, 167, 69, .8)',
                'rgba(255, 193, 7, .8)',
                'rgba(220, 53, 69, .8)',
                'rgba(23, 162, 184, .8)',
                'rgba(111, 66, 193, .8)',
                'rgba(253, 126, 20, .8)',
                'rgba(108, 117, 125, .8)'
            ];

            if (window.Chart && document.getElementById('market-seeding-sales-chart')) {
                new Chart(document.getElementById('market-seeding-sales-chart'), {
                    type: 'bar',
                    data: {
                        labels: salesChartData.labels || [],
                        datasets: [
                            {
                                label: 'Estimated Sold',
                                data: (salesChartData.series || {}).estimated_sold || [],
                                backgroundColor: 'rgba(0, 123, 255, .65)',
                                borderColor: 'rgba(0, 123, 255, 1)',
                                borderWidth: 1
                            },
                            {
                                label: 'Restocked',
                                data: (salesChartData.series || {}).restocked || [],
                                backgroundColor: 'rgba(40, 167, 69, .35)',
                                borderColor: 'rgba(40, 167, 69, 1)',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        maintainAspectRatio: false,
                        responsive: true,
                        scales: {
                            yAxes: [{
                                ticks: {
                                    beginAtZero: true,
                                    precision: 0
                                }
                            }]
                        },
                        legend: {
                            position: 'bottom'
                        },
                        title: {
                            display: true,
                            text: 'Estimated Market Movement, ' + historyCoverageDays + ' Days With Data'
                        }
                    }
                });
            }

            if (window.Chart && document.getElementById('market-seeding-category-chart')) {
                new Chart(document.getElementById('market-seeding-category-chart'), {
                    type: 'doughnut',
                    data: {
                        labels: (categorySales || []).map(function (row) { return row.type_category; }),
                        datasets: [{
                            data: (categorySales || []).map(function (row) { return row.estimated_sold; }),
                            backgroundColor: categoryColors
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        responsive: true,
                        legend: {
                            position: 'bottom'
                        },
                        title: {
                            display: true,
                            text: 'Estimated Sold Quantity By Category'
                        }
                    }
                });
            }

            if (window.Chart && document.getElementById('market-seeding-history-chart')) {
                new Chart(document.getElementById('market-seeding-history-chart'), {
                    type: 'bar',
                    data: {
                        labels: chartData.labels || [],
                        datasets: [
                            {
                                label: 'Low',
                                data: (chartData.series || {}).low || [],
                                backgroundColor: 'rgba(255, 193, 7, .75)'
                            },
                            {
                                label: 'Empty',
                                data: (chartData.series || {}).empty || [],
                                backgroundColor: 'rgba(220, 53, 69, .75)'
                            },
                            {
                                label: 'Recovered',
                                data: (chartData.series || {}).stocked || [],
                                backgroundColor: 'rgba(40, 167, 69, .75)'
                            }
                        ]
                    },
                    options: {
                        maintainAspectRatio: false,
                        responsive: true,
                        scales: {
                            xAxes: [{
                                stacked: true
                            }],
                            yAxes: [{
                                stacked: true,
                                ticks: {
                                    beginAtZero: true,
                                    precision: 0
                                }
                            }]
                        },
                        legend: {
                            position: 'bottom'
                        },
                        title: {
                            display: true,
                            text: 'Stock Transitions, ' + historyCoverageDays + ' Days With Data'
                        }
                    }
                });
            }

            if ($.fn.DataTable) {
                if ($('.market-seeding-attention-table').length) {
                    $('.market-seeding-attention-table').DataTable({
                        order: [[4, 'desc']],
                        pageLength: 10,
                        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
                        searching: true,
                        info: true,
                        autoWidth: false,
                        language: {
                            emptyTable: 'No recommendations need attention for the current filters.',
                            zeroRecords: 'No recommendation rows match this search.'
                        }
                    });
                }

                $('.market-seeding-top-sold-table').DataTable({
                    order: [[2, 'desc']],
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
                    searching: true,
                    info: true,
                    autoWidth: false,
                    language: {
                        emptyTable: 'No estimated sales have been recorded yet.',
                        zeroRecords: 'No sold items match this search.'
                    }
                });

                $('.market-seeding-restock-needs-table').DataTable({
                    order: [[2, 'desc']],
                    pageLength: 5,
                    lengthMenu: [[5, 10, 25, -1], [5, 10, 25, 'All']],
                    searching: true,
                    info: true,
                    autoWidth: false,
                    language: {
                        emptyTable: 'No low or empty restock events have been recorded yet.',
                        zeroRecords: 'No restock needs match this search.'
                    }
                });

                $('.market-seeding-history-table').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: $('.market-seeding-history-table').data('ajax-url'),
                    order: [[0, 'desc']],
                    pageLength: 25,
                    lengthMenu: [[25, 50, 100], [25, 50, 100]],
                    searching: true,
                    info: true,
                    autoWidth: false,
                    language: {
                        emptyTable: 'No stock transitions have been recorded yet.',
                        zeroRecords: 'No history entries match this search.'
                    }
                });
            }

            $(document).on('click', '.market-seeding-edit-target', function () {
                var $button = $(this);
                var updateUrl = $button.data('update-url') || '';
                var readOnly = !canManageMarketSeeding || !updateUrl;

                $('#market-seeding-edit-target-title').text(readOnly ? 'Item Details' : 'Edit Target Stock');
                $('#market-seeding-edit-target-modal').toggleClass('is-read-only', readOnly);
                $('#market-seeding-edit-target-adjust-panel').toggle(!readOnly);
                $('#market-seeding-edit-target-save').toggle(!readOnly);
                $('#market-seeding-edit-target-form').attr('action', updateUrl);
                $('#market-seeding-edit-target-item').text($button.data('item-name'));
                $('#market-seeding-edit-target-market').text($button.data('market-name'));
                $('#market-seeding-edit-target-icon').addClass('d-none').attr('src', '').attr('alt', '');
	                $('#market-seeding-edit-target-quantity').val($button.data('desired-quantity'));
	                $('#market-seeding-edit-warning-quantity').val($button.data('warning-quantity'));
	                $('#market-seeding-edit-target-form')
	                    .data('original-target-quantity', parseInt($button.data('desired-quantity') || 1, 10))
	                    .data('original-warning-quantity', parseInt($button.data('warning-quantity') || 0, 10));
                renderRecommendationCard($button);
                $('#market-seeding-edit-target-success').addClass('d-none').text('');
                $('#market-seeding-edit-target-error').addClass('d-none').text('');
                $('#market-seeding-edit-target-form').data('trigger-url', updateUrl);
                resetTargetDetails();
                loadTargetHistory($button.data('history-url'));
                $('#market-seeding-edit-target-modal').modal('show');
            });

	            $('#market-seeding-use-recommended-target').on('click', function () {
	                $('#market-seeding-edit-target-quantity')
	                    .val($(this).data('recommended-quantity'))
	                    .trigger('input');
	            });

	            $('#market-seeding-edit-target-quantity').on('input change', function () {
	                scaleEditWarningFromTarget();
	                updateTargetDetailProjection();
	            });

	            $('#market-seeding-edit-warning-quantity').on('input change', function () {
	                clampEditWarningToTarget();
	            });

            $('#market-seeding-edit-target-form').on('submit', function (event) {
                event.preventDefault();

                if (!canManageMarketSeeding) {
                    return;
                }

                var $form = $(this);
                var $save = $('#market-seeding-edit-target-save');

                $save.prop('disabled', true).text('Saving...');
                $('#market-seeding-edit-target-success').addClass('d-none').text('');
                $('#market-seeding-edit-target-error').addClass('d-none').text('');

                $.ajax({
                    url: $form.attr('action'),
                    method: 'POST',
                    data: $form.serialize(),
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                }).done(function (response) {
                    var item = response.item || {};
                    var updateUrl = $form.data('trigger-url');

                    $('.market-seeding-edit-target[data-update-url="' + updateUrl + '"]')
                        .data('desired-quantity', item.desired_quantity)
                        .data('warning-quantity', item.warning_quantity);

                    $('#market-seeding-edit-target-quantity').val(item.desired_quantity);
                    $('#market-seeding-edit-warning-quantity').val(item.warning_quantity);
                    currentTargetDetails.desired_quantity = parseInt(item.desired_quantity || 0, 10);
                    currentTargetDetails.warning_quantity = parseInt(item.warning_quantity || 0, 10);
                    updateTargetDetailProjection();
	                    $('#market-seeding-edit-target-success')
	                        .removeClass('d-none')
	                        .text((response.message || 'Target stock updated.') + ' Refreshing history...');

	                    if ($.fn.DataTable && $.fn.DataTable.isDataTable('.market-seeding-history-table')) {
	                        $('.market-seeding-history-table').DataTable().ajax.reload(null, false);
	                    }

	                    window.setTimeout(function () {
	                        window.location.reload();
	                    }, 700);
	                }).fail(function (xhr) {
                    var message = 'Unable to update target stock.';

                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }

                    $('#market-seeding-edit-target-error').removeClass('d-none').text(message);
                }).always(function () {
                    $save.prop('disabled', false).text('Save Target');
                });
            });

	            function numberWithCommas(value) {
                value = parseInt(value || 0, 10);

                return value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
	            }

	            function scaleEditWarningFromTarget() {
	                var $form = $('#market-seeding-edit-target-form');
	                var targetQuantity = Math.max(1, parseInt($('#market-seeding-edit-target-quantity').val() || 1, 10));
	                var originalTarget = Math.max(1, parseInt($form.data('original-target-quantity') || 1, 10));
	                var originalWarning = Math.max(0, parseInt($form.data('original-warning-quantity') || 0, 10));
	                var scaledWarning = originalWarning === 0
	                    ? 0
	                    : Math.ceil(targetQuantity * (originalWarning / originalTarget));

	                $('#market-seeding-edit-warning-quantity').val(Math.min(targetQuantity, Math.max(0, scaledWarning)));
	            }

	            function clampEditWarningToTarget() {
	                var targetQuantity = Math.max(1, parseInt($('#market-seeding-edit-target-quantity').val() || 1, 10));
	                var warningQuantity = Math.max(0, parseInt($('#market-seeding-edit-warning-quantity').val() || 0, 10));

	                if (warningQuantity > targetQuantity) {
	                    $('#market-seeding-edit-warning-quantity').val(targetQuantity);
	                }
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

            function formatCurrency(value) {
                value = parseFloat(value);

                if (!isFinite(value)) {
                    value = 0;
                }

                return '$' + value.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            function formatDecimal(value, decimals) {
                value = parseFloat(value);

                if (!isFinite(value)) {
                    return '-';
                }

                return value.toLocaleString('en-US', {
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals
                });
            }

            function renderRecommendationCard($button) {
                var currentTarget = parseInt($button.data('desired-quantity') || 0, 10);
                var recommendedTarget = parseInt($button.data('recommended-quantity') || currentTarget || 0, 10);
                var estimatedSold = parseInt($button.data('recommendation-estimated-sold') || 0, 10);
                var daysWithData = parseInt($button.data('recommendation-days-with-data') || 0, 10);
                var dailySold = parseFloat($button.data('recommendation-daily-sold') || 0);
                var salesWindow = parseInt($button.data('recommendation-sales-window') || 0, 10);
                var bufferMultiplier = parseFloat($button.data('recommendation-buffer-multiplier') || 1);
                var salesTarget = parseInt($button.data('recommendation-sales-target') || recommendedTarget || 0, 10);
                var existingTargetCovers = parseInt($button.data('recommendation-existing-target-covers') || 0, 10) === 1;
                var math = numberWithCommas(estimatedSold) + ' sold / ' + numberWithCommas(daysWithData) +
                    ' days = ' + formatDecimal(dailySold, 2) + '/day';
                var formula = formatDecimal(dailySold, 2) + ' x ' + numberWithCommas(salesWindow) +
                    ' days x ' + formatDecimal(bufferMultiplier, 2) + ' buffer = ' + numberWithCommas(salesTarget);
                var result = existingTargetCovers
                    ? 'Current target of ' + numberWithCommas(currentTarget) + ' already covers the sales recommendation.'
                    : 'Recommended target increases to ' + numberWithCommas(recommendedTarget) + ' based on recent sales.';

                $('#market-seeding-edit-target-recommended-value').text(numberWithCommas(salesTarget));
                $('#market-seeding-edit-target-recommendation-math').html(
                    escapeHtml(math) + '<br>' + escapeHtml(formula)
                );
                $('#market-seeding-edit-target-recommendation-result').text(result);
                $('#market-seeding-use-recommended-target').text('Use');
                $('#market-seeding-use-recommended-target').data('recommended-quantity', salesTarget);
            }

            function formatSignedWhole(value) {
                value = parseInt(value || 0, 10);

                if (value === 0) {
                    return 'No change';
                }

                return (value > 0 ? '+' : '-') + numberWithCommas(Math.abs(value));
            }

            function formatSignedCurrency(value) {
                value = parseFloat(value || 0);

                if (!isFinite(value) || value === 0) {
                    return 'No change';
                }

                return (value > 0 ? '+' : '-') + formatMoney(Math.abs(value));
            }

            function formatSignedVolume(value) {
                value = parseFloat(value || 0);

                if (!isFinite(value) || value === 0) {
                    return 'No change';
                }

                return (value > 0 ? '+' : '-') + formatDecimal(Math.abs(value), 2) + ' m3';
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

            function setDeltaText(selector, value, formatter) {
                var $element = $(selector);
                var numeric = parseFloat(value || 0);

                $element
                    .removeClass('is-positive is-negative')
                    .addClass(numeric > 0 ? 'is-positive' : (numeric < 0 ? 'is-negative' : ''))
                    .text(formatter(value));
            }

            function resetTargetDetails() {
                currentTargetDetails = {};
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
                $('#market-seeding-detail-missing-delta').text('').removeClass('is-positive is-negative');
                $('#market-seeding-detail-target-value-delta').text('').removeClass('is-positive is-negative');
                $('#market-seeding-detail-restock-value-delta').text('').removeClass('is-positive is-negative');
                $('#market-seeding-detail-restock-volume-delta').text('').removeClass('is-positive is-negative');
                $('#market-seeding-detail-trend-summary').text('Loading...');
                $('#market-seeding-detail-source-badges').html('');
                $('#market-seeding-detail-source-list').html('<div class="text-muted">Loading source details...</div>');
                if (targetTrendChart) {
                    targetTrendChart.destroy();
                    targetTrendChart = null;
                }
            }

            function renderTargetDetails(details) {
                details = details || {};
                currentTargetDetails = $.extend({}, details);

                $('#market-seeding-detail-current').text(numberWithCommas(details.current_quantity));
	                $('#market-seeding-detail-local-price').text(formatMoney(details.local_price || details.jita_price));
                $('#market-seeding-detail-jita-price').text(formatMoney(details.jita_price));
                $('#market-seeding-detail-seeded-value').text(formatMoney(details.seeded_value));
                $('#market-seeding-detail-item-volume').text(formatDecimal(details.item_volume, 2) + ' m3 each, packaged');
                updateTargetDetailProjection();

	                if (details.price_delta === null || typeof details.price_delta === 'undefined') {
	                    $('#market-seeding-detail-price-delta').text(details.jita_price ? 'No local market price' : 'No Jita comparison');
                } else {
                    var delta = parseFloat(details.price_delta);
                    var prefix = delta > 0 ? '+' : '';

                    $('#market-seeding-detail-price-delta').text(prefix + formatDecimal(delta, 1) + '% vs Jita');
                }
            }

            function renderItemHeader(item) {
                item = item || {};
                var iconUrl = eveTypeIconUrl(item.type_id, 64);
                var $icon = $('#market-seeding-edit-target-icon');

                if (!iconUrl) {
                    $icon.addClass('d-none').attr('src', '').attr('alt', '');
                    return;
                }

                $icon
                    .removeClass('d-none')
                    .attr('src', iconUrl)
                    .attr('alt', item.type_name ? item.type_name + ' icon' : 'Item icon');
            }

            function renderSourceDetails(sourceDetails) {
                sourceDetails = sourceDetails || {};
                var flags = sourceDetails.flags || {};
                var manualSources = sourceDetails.manual || [];
                var doctrines = sourceDetails.doctrines || [];
                var $badges = $('#market-seeding-detail-source-badges');
                var $list = $('#market-seeding-detail-source-list');

                $badges.empty();
                $list.empty();

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
                    var fits = doctrine.fits || [];
                    var fitHtml = '';

                    if (!fits.length) {
                        fitHtml = '<div class="edit-target-source-fit-meta mt-1">This item is linked to the doctrine, but no matching fit breakdown could be loaded.</div>';
                    } else {
                        $.each(fits, function (fitIndex, fit) {
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

            function renderTargetTrend(trend) {
                trend = trend || {};
                var labels = trend.labels || [];
                var values = trend.values || [];

                $('#market-seeding-detail-trend-summary').text(
                    numberWithCommas(trend.total || 0) + ' estimated sold over ' + numberWithCommas(trend.days || selectedDays) + ' days'
                );

                if (targetTrendChart) {
                    targetTrendChart.destroy();
                    targetTrendChart = null;
                }

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
                            borderColor: 'rgba(23, 162, 184, 1)',
                            borderWidth: 2,
                            pointRadius: 2,
                            pointHoverRadius: 4,
                            lineTension: .25
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        responsive: true,
                        legend: {
                            display: false
                        },
                        scales: {
                            yAxes: [{
                                ticks: {
                                    beginAtZero: true,
                                    precision: 0
                                }
                            }]
                        }
                    }
                });
            }

            function updateTargetDetailProjection() {
                var targetQuantity = parseInt($('#market-seeding-edit-target-quantity').val(), 10);

                if (!isFinite(targetQuantity)) {
                    targetQuantity = parseInt(currentTargetDetails.desired_quantity || 0, 10);
                }

                targetQuantity = Math.max(0, targetQuantity || 0);

                var currentQuantity = parseInt(currentTargetDetails.current_quantity || 0, 10);
                var missingQuantity = Math.max(0, targetQuantity - currentQuantity);
                var jitaPrice = parseFloat(currentTargetDetails.jita_price || 0);
                var itemVolume = parseFloat(currentTargetDetails.item_volume || 0);
                var targetValue = targetQuantity * jitaPrice;
                var restockValue = missingQuantity * jitaPrice;
                var restockVolume = missingQuantity * itemVolume;
                var originalMissing = parseInt(currentTargetDetails.missing_quantity || 0, 10);
                var originalTargetValue = parseFloat(currentTargetDetails.desired_value || 0);
                var originalRestockValue = parseFloat(currentTargetDetails.restock_cost || 0);
                var originalRestockVolume = parseFloat(currentTargetDetails.restock_volume || 0);

                $('#market-seeding-detail-missing').text(numberWithCommas(missingQuantity));
                $('#market-seeding-detail-hero-missing').text(numberWithCommas(missingQuantity));
                $('#market-seeding-detail-target-value').text(formatMoney(targetValue));
                $('#market-seeding-detail-restock-value').text(formatMoney(restockValue));
                $('#market-seeding-detail-restock-volume').text(formatDecimal(restockVolume, 2) + ' m3');
                setDeltaText('#market-seeding-detail-missing-delta', missingQuantity - originalMissing, formatSignedWhole);
                setDeltaText('#market-seeding-detail-target-value-delta', targetValue - originalTargetValue, formatSignedCurrency);
                setDeltaText('#market-seeding-detail-restock-value-delta', restockValue - originalRestockValue, formatSignedCurrency);
                setDeltaText('#market-seeding-detail-restock-volume-delta', restockVolume - originalRestockVolume, formatSignedVolume);
            }

            function loadTargetHistory(url) {
                var $body = $('#market-seeding-edit-target-history');
                var $targetBody = $('#market-seeding-edit-target-change-history');

                $body.html('<tr><td colspan="5" class="text-muted">Loading transition history...</td></tr>');
                $targetBody.html('<tr><td colspan="5" class="text-muted">Loading target changes...</td></tr>');

                if (!url) {
                    renderItemHeader({});
                    renderTargetDetails({});
                    renderTargetTrend({});
                    renderSourceDetails({});
                    $body.html('<tr><td colspan="5" class="text-muted">No transition history is available for this item.</td></tr>');
                    renderTargetChangeHistory([]);
                    return;
                }

                $.ajax({
                    url: url,
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    }
                }).done(function (response) {
                    var events = response.events || [];

                    renderItemHeader(response.item || {});
                    renderTargetDetails(response.details || {});
                    renderTargetTrend(response.trend || {});
                    renderSourceDetails(response.source_details || {});
                    renderTargetChangeHistory(response.target_history || []);

                    if (!events.length) {
                        $body.html('<tr><td colspan="5" class="text-muted">No stock transitions have been recorded for this item yet.</td></tr>');
                        return;
                    }

                    $body.empty();

                    $.each(events, function (index, event) {
                        $body.append(
                            '<tr>' +
                                '<td data-order="' + (event.created_at_order || 0) + '">' + escapeHtml(event.created_at || '-') + '</td>' +
                                '<td>' + statusHtml(event.previous_status, event.current_status) + '</td>' +
                                '<td class="text-right">' + numberWithCommas(event.current_quantity) + '</td>' +
                                '<td class="text-right">' + numberWithCommas(event.warning_quantity) + '</td>' +
                                '<td class="text-right">' + numberWithCommas(event.desired_quantity) + '</td>' +
                            '</tr>'
                        );
                    });
                }).fail(function () {
                    renderItemHeader({});
                    renderTargetDetails({});
                    renderTargetTrend({});
                    renderSourceDetails({});
                    $body.html('<tr><td colspan="5" class="text-danger">Unable to load transition history.</td></tr>');
                    $targetBody.html('<tr><td colspan="5" class="text-danger">Unable to load target changes.</td></tr>');
                });
            }

            function renderTargetChangeHistory(rows) {
                var $body = $('#market-seeding-edit-target-change-history');

                if (!rows.length) {
                    $body.html('<tr><td colspan="5" class="text-muted">No target changes have been recorded for this item yet.</td></tr>');
                    return;
                }

                $body.empty();

                $.each(rows, function (index, row) {
                    $body.append(
                        '<tr>' +
                            '<td data-order="' + (row.created_at_order || 0) + '">' + escapeHtml(row.created_at || '-') + '</td>' +
                            '<td>' + escapeHtml(row.change_type_label || row.change_type || '-') + '</td>' +
                            '<td>' + escapeHtml(row.user_name || 'System') + '</td>' +
                            '<td class="text-right">' + targetChangeText(row.old_target_quantity, row.new_target_quantity) + '</td>' +
                            '<td class="text-right">' + targetChangeText(row.old_warning_quantity, row.new_warning_quantity) + '</td>' +
                        '</tr>'
                    );
                });
            }

            function targetChangeText(oldValue, newValue) {
                var oldLabel = oldValue === null || typeof oldValue === 'undefined' ? '-' : numberWithCommas(oldValue);
                var newLabel = newValue === null || typeof newValue === 'undefined' ? '-' : numberWithCommas(newValue);

                return escapeHtml(oldLabel) + ' &rarr; ' + escapeHtml(newLabel);
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

            function escapeHtml(value) {
                return $('<div>').text(value || '').html();
            }
        });
    </script>
@endpush
