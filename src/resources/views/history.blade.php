@extends('web::layouts.grids.12')

@section('title', 'Market Seeding History')
@section('page_header', 'Market Seeding History')

@section('content')
    @php
        $activeSkin = setting('skin') ?: 'default';
        $marketSeedingThemeClass = in_array($activeSkin, ['jet', 'iuligigi', 'gigigraphite'], true)
            ? 'market-seeding-dark-skin'
            : '';
        $whole = function ($value) {
            return number_format((float) $value, 0, '.', ',');
        };
        $statusBadge = function ($status) {
            return [
                'stocked' => 'badge-success',
                'low' => 'badge-warning',
                'empty' => 'badge-danger',
            ][$status] ?? 'badge-secondary';
        };
        $attentionRecommendationPayload = $attentionItems->map(function ($item) {
            return [
                'item_id' => (int) $item->item_id,
                'type_name' => $item->type_name,
                'type_category' => $item->type_category,
                'market_name' => $item->market_name,
                'location_name' => $item->location_name,
                'current_target_quantity' => (int) $item->current_target_quantity,
                'recommended_quantity' => (int) $item->recommended_quantity,
                'recommendation_reason' => $item->recommendation_reason,
                'recommendation_delta_cost' => (float) ($item->recommendation_delta_cost ?? 0),
                'recommendation_delta_volume' => (float) ($item->recommendation_delta_volume ?? 0),
            ];
        })->values();
        $historyCsrfToken = csrf_token();
        $recommendationApplyUrl = route('market-seeding.history.recommendations.apply');
        $recommendationFilters = request()->only('market_id', 'status', 'type_category', 'days');
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
        .market-seeding-history-shell .history-recommendation-pill {
            background: rgba(220, 53, 69, .08);
            border: 1px solid rgba(220, 53, 69, .35);
            border-radius: 999px;
            color: #b21f2d;
            display: inline-block;
            font-size: .72rem;
            font-weight: 600;
            margin-top: .25rem;
            padding: .05rem .45rem;
        }
        .market-seeding-history-shell .history-recommendation-config {
            color: #31505c;
        }
        .market-seeding-history-shell .history-attention-card {
            border-left: 4px solid #dc3545;
        }
        .market-seeding-history-shell .history-attention-actions {
            display: flex;
            gap: .5rem;
            justify-content: flex-end;
            margin-left: auto;
            white-space: nowrap;
        }
        .market-seeding-history-shell .history-attention-card .card-header > div:first-child {
            flex: 1;
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
        .market-seeding-recommendations-modal .modal-dialog {
            max-width: 960px;
        }
        .market-seeding-recommendations-modal .recommendation-summary {
            background: linear-gradient(135deg, #fff5f5 0%, #f8f9fa 100%);
            border: 1px solid rgba(220, 53, 69, .18);
            border-radius: 8px;
            display: grid;
            gap: .75rem;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            margin-bottom: 1rem;
            padding: .85rem;
        }
        .market-seeding-recommendations-modal .recommendation-summary-item {
            border-right: 1px solid rgba(220, 53, 69, .16);
            padding-right: .75rem;
        }
        .market-seeding-recommendations-modal .recommendation-summary-item:last-child {
            border-right: 0;
            padding-right: 0;
        }
        .market-seeding-recommendations-modal .recommendation-summary-label {
            color: #6c757d;
            display: block;
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .market-seeding-recommendations-modal .recommendation-summary-value {
            display: block;
            font-size: 1.35rem;
            font-weight: 800;
            line-height: 1.2;
        }
        .market-seeding-recommendations-modal .recommendation-list {
            max-height: 460px;
            overflow-y: auto;
        }
        .market-seeding-recommendations-modal .recommendation-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            display: block;
            margin-bottom: .65rem;
            padding: .8rem;
        }
        .market-seeding-recommendations-modal .recommendation-card:last-child {
            margin-bottom: 0;
        }
        .market-seeding-recommendations-modal .recommendation-card-main {
            align-items: center;
            display: grid;
            gap: .85rem;
            grid-template-columns: minmax(0, 1fr) auto;
            margin-bottom: .75rem;
        }
        .market-seeding-recommendations-modal .recommendation-item-name {
            display: block;
            font-weight: 800;
            line-height: 1.25;
        }
        .market-seeding-recommendations-modal .recommendation-meta {
            color: #6c757d;
            display: block;
            font-size: .8rem;
            margin-top: .15rem;
        }
        .market-seeding-recommendations-modal .recommendation-target-change {
            align-items: center;
            display: flex;
            gap: .45rem;
            justify-content: flex-end;
            white-space: nowrap;
        }
        .market-seeding-recommendations-modal .recommendation-current {
            color: #6c757d;
            font-size: .9rem;
            font-weight: 700;
        }
        .market-seeding-recommendations-modal .recommendation-arrow {
            color: #6c757d;
            font-weight: 800;
        }
        .market-seeding-recommendations-modal .recommendation-new {
            background: rgba(220, 53, 69, .1);
            border: 1px solid rgba(220, 53, 69, .32);
            border-radius: 999px;
            color: #b21f2d;
            font-weight: 800;
            padding: .15rem .55rem;
        }
        .market-seeding-recommendations-modal .recommendation-reason {
            color: #495057;
            font-size: .86rem;
            line-height: 1.35;
        }
        .market-seeding-recommendations-modal .recommendation-delta-grid {
            display: grid;
            gap: .55rem;
            grid-template-columns: minmax(110px, .75fr) minmax(240px, 1.6fr) minmax(150px, 1fr);
        }
        .market-seeding-recommendations-modal .recommendation-delta {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 7px;
            min-width: 0;
            padding: .5rem .65rem;
        }
        .market-seeding-recommendations-modal .recommendation-delta-label {
            color: #6c757d;
            display: block;
            font-size: .65rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .market-seeding-recommendations-modal .recommendation-delta-value {
            display: block;
            font-size: .98rem;
            font-weight: 800;
            line-height: 1.2;
            margin-top: .08rem;
            overflow-wrap: anywhere;
            word-break: normal;
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
        .market-seeding-edit-target-modal .edit-target-workspace {
            display: grid;
            gap: 1rem;
            grid-template-columns: minmax(280px, .9fr) minmax(0, 1.35fr);
        }
        .market-seeding-edit-target-modal .edit-target-panel {
            border: 1px solid #dee2e6;
            border-radius: 8px;
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
            border-radius: 8px;
            margin-bottom: .85rem;
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
        .market-seeding-dark-skin .history-recommendation-pill {
            background: rgba(220, 53, 69, .16);
            border-color: rgba(220, 53, 69, .55);
            color: #ffb3bc;
        }
        .market-seeding-dark-skin .history-recommendation-config {
            color: #d7eef8;
        }
        .market-seeding-dark-skin .history-attention-card {
            border-left-color: #ff9aa7;
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin .history-sparkline polyline {
            stroke: #7bdff2;
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-trend-panel {
            background: #1f292e;
            border-color: #3c4b54;
        }
        .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-trend-summary {
            color: #b8c7ce;
        }
        .market-seeding-dark-skin .history-heatmap-cell {
            color: #f4e7be;
        }
        .market-seeding-recommendations-modal.market-seeding-dark-skin .modal-content {
            background: #2f2927;
            color: #f4e7be;
        }
        .market-seeding-recommendations-modal.market-seeding-dark-skin .modal-header,
        .market-seeding-recommendations-modal.market-seeding-dark-skin .modal-footer {
            border-color: rgba(244, 231, 190, .24);
        }
        .market-seeding-recommendations-modal.market-seeding-dark-skin .close {
            color: #f4e7be;
            opacity: .75;
            text-shadow: none;
        }
        .market-seeding-recommendations-modal.market-seeding-dark-skin .recommendation-summary {
            background: linear-gradient(135deg, #3b3330 0%, #292523 100%);
            border-color: rgba(244, 231, 190, .22);
        }
        .market-seeding-recommendations-modal.market-seeding-dark-skin .recommendation-summary-item {
            border-right-color: rgba(244, 231, 190, .16);
        }
        .market-seeding-recommendations-modal.market-seeding-dark-skin .recommendation-summary-label,
        .market-seeding-recommendations-modal.market-seeding-dark-skin .recommendation-meta,
        .market-seeding-recommendations-modal.market-seeding-dark-skin .recommendation-current,
        .market-seeding-recommendations-modal.market-seeding-dark-skin .recommendation-arrow {
            color: #b9a998;
        }
        .market-seeding-recommendations-modal.market-seeding-dark-skin .recommendation-card {
            background: #1f292e;
            border-color: #3c4b54;
        }
        .market-seeding-recommendations-modal.market-seeding-dark-skin .recommendation-delta {
            background: #25343a;
            border-color: #3c4b54;
        }
        .market-seeding-recommendations-modal.market-seeding-dark-skin .recommendation-delta-label {
            color: #b8c7ce;
        }
        .market-seeding-recommendations-modal.market-seeding-dark-skin .recommendation-new {
            background: rgba(220, 53, 69, .24);
            border-color: rgba(255, 154, 167, .42);
            color: #ffb3bc;
        }
        .market-seeding-recommendations-modal.market-seeding-dark-skin .recommendation-reason {
            color: #d7eef8;
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
            .market-seeding-recommendations-modal .recommendation-card {
                grid-template-columns: 1fr;
            }
            .market-seeding-recommendations-modal .recommendation-card-main,
            .market-seeding-recommendations-modal .recommendation-delta-grid {
                grid-template-columns: 1fr;
            }
            .market-seeding-recommendations-modal .recommendation-target-change {
                justify-content: flex-start;
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
            .market-seeding-edit-target-modal .edit-target-form-grid,
            .market-seeding-recommendations-modal .recommendation-summary {
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
                        <div class="history-stat-label">Average Daily Sold</div>
                        <div class="history-stat-value">{{ number_format($salesSummary['average_daily_sold'], 1, '.', ',') }}</div>
                        <div class="history-stat-help">Across the selected {{ $days }} day window.</div>
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

                <div class="alert alert-info">
                    Sold quantities are estimated from changes in available sell-order quantity between ESI refreshes. They are great for seeding trends, but can include delisted or expired orders.
                </div>

                <div class="card history-attention-card">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title mb-0">Needs Attention</h3>
                            <small class="text-muted">Items where recent movement suggests a higher target stock amount.</small>
                            <small class="text-muted d-block">Restock Pace estimates how often the item becomes low or empty in this history window.</small>
                        </div>
                        @can('seat-market-seeding.manager')
                            <div class="history-attention-actions">
                                <button type="button"
                                        class="btn btn-danger btn-sm"
                                        id="market-seeding-review-recommendations"
                                        {{ $attentionItems->isEmpty() ? 'disabled' : '' }}>
                                    <i class="fas fa-check-double"></i> Review Recommendations
                                </button>
                            </div>
                        @endcan
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover market-seeding-attention-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Market</th>
                                        <th class="text-right">Current</th>
                                        <th class="text-right">Recommended</th>
                                        <th class="text-right">Gap</th>
                                        <th class="text-right">Sold</th>
                                        <th class="text-right" title="Average time between low or empty restock-needed events in the selected history window. Lower values mean the item needs attention more often.">Restock Pace</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($attentionItems as $item)
                                        <tr>
                                            <td>
                                                {{ $item->type_name }}
                                                <div class="text-muted small">{{ $item->type_category }}</div>
                                            </td>
                                            <td>
                                                {{ $item->market_name }}
                                                <div class="text-muted small">{{ $item->location_name }}</div>
                                            </td>
                                            <td class="text-right" data-order="{{ $item->current_target_quantity }}">{{ $whole($item->current_target_quantity) }}</td>
                                            <td class="text-right" data-order="{{ $item->recommended_quantity }}">
                                                <span class="history-recommendation-pill">Recommended {{ $whole($item->recommended_quantity) }}</span>
                                            </td>
                                            <td class="text-right" data-order="{{ $item->recommended_quantity - $item->current_target_quantity }}">{{ $whole($item->recommended_quantity - $item->current_target_quantity) }}</td>
                                            <td class="text-right" data-order="{{ $item->estimated_sold }}">{{ $whole($item->estimated_sold) }}</td>
                                            <td class="text-right" data-order="{{ $item->average_days_between_restock_needs ?? 999999 }}" title="Average time between low or empty restock-needed events in the selected {{ $days }} day window.">
                                                {{ $item->average_days_between_restock_needs ? 'Every ' . number_format($item->average_days_between_restock_needs, 1, '.', ',') . ' days' : '-' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="text-muted">No recommendations need attention for the current filters.</td>
                                            <td class="text-muted">-</td>
                                            <td class="text-right">-</td>
                                            <td class="text-right">-</td>
                                            <td class="text-right">-</td>
                                            <td class="text-right">-</td>
                                            <td class="text-right">-</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
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
                                <small class="text-muted">Top categories in the selected window.</small>
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
                            <small class="text-muted">Darker cells mean more sold plus restocked movement in the selected window.</small>
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
                                        <th class="text-right">Current</th>
                                        <th class="text-right">Avg / Day</th>
                                        <th class="text-right">Restocked</th>
                                        <th class="text-right">Sales Events</th>
                                        <th>Last Sold</th>
                                        @can('seat-market-seeding.manager')
                                            <th class="text-right history-actions-column">Actions</th>
                                        @endcan
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($topSoldItems as $item)
                                        <tr>
                                            <td>
                                                {{ $item->type_name }}
                                                <div class="text-muted small">{{ $item->type_category }}</div>
                                                @if($item->recommendation_differs)
                                                    <div class="history-recommendation-pill">Target {{ $whole($item->current_target_quantity) }} &rarr; Recommended {{ $whole($item->recommended_quantity) }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                {{ $item->market_name }}
                                                <div class="text-muted small">{{ $item->location_name }}</div>
                                            </td>
                                            <td class="text-right" data-order="{{ $item->estimated_sold }}">{{ $whole($item->estimated_sold) }}</td>
                                            <td class="text-right" data-order="{{ $item->latest_seen_quantity }}">{{ $whole($item->latest_seen_quantity) }}</td>
                                            <td class="text-right" data-order="{{ $days ? $item->estimated_sold / $days : 0 }}">{{ number_format($days ? $item->estimated_sold / $days : 0, 1, '.', ',') }}</td>
                                            <td class="text-right" data-order="{{ $item->restocked }}">{{ $whole($item->restocked) }}</td>
                                            <td class="text-right" data-order="{{ $item->sales_events }}">{{ $whole($item->sales_events) }}</td>
                                            <td data-order="{{ $item->last_sold_at ? \Carbon\Carbon::parse($item->last_sold_at)->timestamp : 0 }}">
                                                {{ $item->last_sold_at ? \Carbon\Carbon::parse($item->last_sold_at)->format('Y-m-d H:i') : '-' }}
                                            </td>
                                            @can('seat-market-seeding.manager')
                                                <td class="text-right">
                                                    @if($item->item_id)
                                                        <button type="button"
                                                                class="btn btn-link btn-xs p-0 history-item-action market-seeding-edit-target"
                                                                title="Edit target stock"
                                                                data-update-url="{{ route('market-seeding.items.update', $item->item_id) }}"
                                                                data-item-name="{{ $item->type_name }}"
                                                                data-market-name="{{ $item->market_name }}"
                                                                data-history-url="{{ route('market-seeding.items.history', ['item' => $item->item_id, 'days' => $days]) }}"
                                                                data-desired-quantity="{{ (int) $item->target_quantity }}"
                                                                data-warning-quantity="{{ (int) $item->warning_quantity }}"
                                                                data-recommended-quantity="{{ (int) $item->recommended_quantity }}"
                                                                data-recommendation-reason="{{ $item->recommendation_reason }}">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    @endif
                                                </td>
                                            @endcan
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
                                            @can('seat-market-seeding.manager')
                                                <td class="text-right">-</td>
                                            @endcan
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
                                <small class="text-muted">Low, empty, and recovered status changes in the selected window.</small>
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
                            <small class="text-muted d-block">Restock Pace is the average time between low or empty restock-needed events. Lower is busier.</small>
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
                                        <th class="text-right" title="Average time between low or empty restock-needed events in the selected history window. Lower values mean the item needs attention more often.">Restock Pace</th>
                                        <th>Last Needed</th>
                                        @can('seat-market-seeding.manager')
                                            <th class="text-right history-actions-column">Actions</th>
                                        @endcan
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($restockLeaders as $leader)
                                        <tr>
                                            <td>
                                                {{ $leader->type_name }}
                                                <div class="text-muted small">{{ $leader->type_category }}</div>
                                                @if($leader->recommendation_differs)
                                                    <div class="history-recommendation-pill">Target {{ $whole($leader->current_target_quantity) }} &rarr; Recommended {{ $whole($leader->recommended_quantity) }}</div>
                                                @endif
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
                                            <td class="text-right" data-order="{{ $leader->average_days_between_restock_needs ?? 999999 }}" title="Average time between low or empty restock-needed events in the selected {{ $days }} day window.">
                                                {{ $leader->average_days_between_restock_needs ? 'Every ' . number_format($leader->average_days_between_restock_needs, 1, '.', ',') . ' days' : '-' }}
                                            </td>
                                            <td data-order="{{ $leader->last_needed_at ? \Carbon\Carbon::parse($leader->last_needed_at)->timestamp : 0 }}">
                                                {{ $leader->last_needed_at ? \Carbon\Carbon::parse($leader->last_needed_at)->format('Y-m-d H:i') : '-' }}
                                            </td>
                                            @can('seat-market-seeding.manager')
                                                <td class="text-right">
                                                    @if($leader->item_id)
                                                        <button type="button"
                                                                class="btn btn-link btn-xs p-0 history-item-action market-seeding-edit-target"
                                                                title="Edit target stock"
                                                                data-update-url="{{ route('market-seeding.items.update', $leader->item_id) }}"
                                                                data-item-name="{{ $leader->type_name }}"
                                                                data-market-name="{{ $leader->market_name }}"
                                                                data-history-url="{{ route('market-seeding.items.history', ['item' => $leader->item_id, 'days' => $days]) }}"
                                                                data-desired-quantity="{{ (int) $leader->desired_quantity }}"
                                                                data-warning-quantity="{{ (int) $leader->warning_quantity }}"
                                                                data-recommended-quantity="{{ (int) $leader->recommended_quantity }}"
                                                                data-recommendation-reason="{{ $leader->recommendation_reason }}">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    @endif
                                                </td>
                                            @endcan
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="text-muted">No low or empty restock events have been recorded yet.</td>
                                            <td class="text-muted">-</td>
                                            <td class="text-right" data-order="0">0</td>
                                            <td class="text-right" data-order="0"><span class="badge badge-danger">0</span></td>
                                            <td class="text-right" data-order="0"><span class="badge badge-warning">0</span></td>
                                            <td class="text-right" data-order="0">0</td>
                                            <td class="text-right" data-order="999999" title="Average time between low or empty restock-needed events in the selected history window.">-</td>
                                            <td data-order="0">-</td>
                                            @can('seat-market-seeding.manager')
                                                <td class="text-right">-</td>
                                            @endcan
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
                                <th class="text-right">Current</th>
                                <th class="text-right">Warning</th>
                                <th class="text-right">Target</th>
                                @can('seat-market-seeding.manager')
                                    <th class="text-right history-actions-column">Actions</th>
                                @endcan
                            </tr>
                        </thead>
	                        <tbody></tbody>
	                    </table>
	                </div>
	            </div>
        </div>
    </div>

    @can('seat-market-seeding.manager')
        <div class="modal fade market-seeding-recommendations-modal {{ $marketSeedingThemeClass }}" id="market-seeding-recommendations-modal" tabindex="-1" role="dialog" aria-labelledby="market-seeding-recommendations-title" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="market-seeding-recommendations-title">Apply Recommended Targets</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger d-none" id="market-seeding-recommendations-error"></div>
                        <div class="recommendation-summary">
                            <div class="recommendation-summary-item">
                                <span class="recommendation-summary-label">Items</span>
                                <span class="recommendation-summary-value" id="market-seeding-recommendations-count">0</span>
                            </div>
                            <div class="recommendation-summary-item">
                                <span class="recommendation-summary-label">Total Target Increase</span>
                                <span class="recommendation-summary-value" id="market-seeding-recommendations-gap">0</span>
                            </div>
                            <div class="recommendation-summary-item">
                                <span class="recommendation-summary-label">&Delta; Cost</span>
                                <span class="recommendation-summary-value" id="market-seeding-recommendations-cost">$0.00</span>
                            </div>
                            <div class="recommendation-summary-item">
                                <span class="recommendation-summary-label">&Delta; Volume</span>
                                <span class="recommendation-summary-value" id="market-seeding-recommendations-volume">0.00 m3</span>
                            </div>
                        </div>
                        <p class="text-muted">
                            Review these target changes before applying them. Cost and volume are the additional seed requirements from raising the targets, not the total market value.
                        </p>
                        <div class="recommendation-list" id="market-seeding-recommendations-body">
                            <div class="text-muted">No recommendations selected.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="market-seeding-apply-recommendations">
                            Apply Recommendations
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade market-seeding-edit-target-modal {{ $marketSeedingThemeClass }}" id="market-seeding-edit-target-modal" tabindex="-1" role="dialog" aria-labelledby="market-seeding-edit-target-title" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <form method="POST" class="modal-content" id="market-seeding-edit-target-form">
                    {{ csrf_field() }}
                    {{ method_field('PUT') }}
                    <div class="modal-header">
                        <h5 class="modal-title" id="market-seeding-edit-target-title">Edit Target Stock</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-success d-none" id="market-seeding-edit-target-success"></div>
                        <div class="alert alert-danger d-none" id="market-seeding-edit-target-error"></div>
                        <div class="edit-target-hero">
                            <div>
                                <span class="edit-target-item-name" id="market-seeding-edit-target-item"></span>
                                <span class="edit-target-market-name" id="market-seeding-edit-target-market"></span>
                            </div>
                            <div class="edit-target-restock-callout">
                                <span class="edit-target-restock-label">Restock Needed</span>
                                <span class="edit-target-restock-value" id="market-seeding-detail-hero-missing">-</span>
                            </div>
                        </div>
                        <div class="edit-target-detail-grid" id="market-seeding-edit-target-details">
                            <div class="edit-target-detail">
                                <span class="edit-target-detail-label">Current Stock</span>
                                <span class="edit-target-detail-value" id="market-seeding-detail-current">-</span>
                                <span class="edit-target-detail-note">Listed on this market</span>
                            </div>
                            <div class="edit-target-detail">
                                <span class="edit-target-detail-label">Missing</span>
                                <span class="edit-target-detail-value" id="market-seeding-detail-missing">-</span>
                                <span class="edit-target-delta" id="market-seeding-detail-missing-delta"></span>
                                <span class="edit-target-detail-note">Needed to hit target</span>
                            </div>
                            <div class="edit-target-detail">
                                <span class="edit-target-detail-label">Market Price</span>
                                <span class="edit-target-detail-value" id="market-seeding-detail-local-price">-</span>
                                <span class="edit-target-detail-note" id="market-seeding-detail-price-delta">vs Jita</span>
                            </div>
                            <div class="edit-target-detail">
                                <span class="edit-target-detail-label">Jita Price</span>
                                <span class="edit-target-detail-value" id="market-seeding-detail-jita-price">-</span>
                                <span class="edit-target-detail-note">Per item estimate</span>
                            </div>
                            <div class="edit-target-detail">
                                <span class="edit-target-detail-label">Seeded Value</span>
                                <span class="edit-target-detail-value" id="market-seeding-detail-seeded-value">-</span>
                                <span class="edit-target-detail-note">Current stock value</span>
                            </div>
                            <div class="edit-target-detail">
                                <span class="edit-target-detail-label">Target Value</span>
                                <span class="edit-target-detail-value" id="market-seeding-detail-target-value">-</span>
                                <span class="edit-target-delta" id="market-seeding-detail-target-value-delta"></span>
                                <span class="edit-target-detail-note">Full target at Jita</span>
                            </div>
                            <div class="edit-target-detail">
                                <span class="edit-target-detail-label">Restock Value</span>
                                <span class="edit-target-detail-value" id="market-seeding-detail-restock-value">-</span>
                                <span class="edit-target-delta" id="market-seeding-detail-restock-value-delta"></span>
                                <span class="edit-target-detail-note">Missing amount at Jita</span>
                            </div>
                            <div class="edit-target-detail">
                                <span class="edit-target-detail-label">Restock Volume</span>
                                <span class="edit-target-detail-value" id="market-seeding-detail-restock-volume">-</span>
                                <span class="edit-target-delta" id="market-seeding-detail-restock-volume-delta"></span>
                                <span class="edit-target-detail-note" id="market-seeding-detail-item-volume">Packaged m3</span>
                            </div>
                        </div>
                        <div class="edit-target-trend-panel">
                            <div class="edit-target-trend-header">
                                <div class="edit-target-trend-title">Estimated Sales Trend</div>
                                <div class="edit-target-trend-summary" id="market-seeding-detail-trend-summary">Loading...</div>
                            </div>
                            <div class="edit-target-trend-chart">
                                <canvas id="market-seeding-detail-trend-chart"></canvas>
                            </div>
                        </div>
                        <div class="edit-target-workspace">
                            <div class="edit-target-panel">
                                <div class="edit-target-panel-title">Adjust Target</div>
                                <div class="alert alert-info" id="market-seeding-edit-target-recommendation">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong>Recommended target: <span id="market-seeding-edit-target-recommended-value"></span></strong>
                                            <div id="market-seeding-edit-target-recommended-reason" class="small mt-1"></div>
                                            <div class="small history-recommendation-config mt-1">Configured from {{ $recommendationSalesDays }} sales days plus a {{ $recommendationBufferPercentage }}% buffer.</div>
                                        </div>
                                        <button type="button" class="btn btn-info btn-sm ml-3" id="market-seeding-use-recommended-target">Use</button>
                                    </div>
                                </div>
                                <div class="edit-target-form-grid">
                                    <div class="form-group mb-0">
                                        <label for="market-seeding-edit-target-quantity">Target Stock</label>
                                        <input type="number" min="1" class="form-control" id="market-seeding-edit-target-quantity" name="desired_quantity" required>
                                    </div>
                                    <div class="form-group mb-0">
                                        <label for="market-seeding-edit-warning-quantity">Low Warning</label>
                                        <input type="number" min="0" class="form-control" id="market-seeding-edit-warning-quantity" name="warning_quantity">
                                    </div>
                                </div>
                            </div>
                            <div class="edit-target-panel">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="edit-target-panel-title mb-0">Recent Stock Transitions</div>
                                    <small class="text-muted">Latest 25 events</small>
                                </div>
                                <div class="modal-history-table">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>When</th>
                                                <th>Status</th>
                                                <th class="text-right">Current</th>
                                                <th class="text-right">Warning</th>
                                                <th class="text-right">Target</th>
                                            </tr>
                                        </thead>
                                        <tbody id="market-seeding-edit-target-history">
                                            <tr>
                                                <td colspan="5" class="text-muted">Select an item to load transition history.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="market-seeding-edit-target-save">Save Target</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endsection

@push('javascript')
    <script>
        $(function () {
            var chartData = @json($chartData);
            var salesChartData = @json($salesChartData);
            var categorySales = @json($categorySales);
            var selectedDays = @json($days);
            var csrfToken = @json($historyCsrfToken);
            var currentTargetDetails = {};
            var targetTrendChart = null;
            var recommendationApplyUrl = @json($recommendationApplyUrl);
            var recommendationFilters = @json($recommendationFilters);
            var attentionRecommendations = @json($attentionRecommendationPayload);
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
                            text: 'Estimated Market Movement, Last ' + selectedDays + ' Days'
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
                            text: 'Stock Transitions, Last ' + selectedDays + ' Days'
                        }
                    }
                });
            }

            if ($.fn.DataTable) {
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

            $('#market-seeding-review-recommendations').on('click', function () {
                var $body = $('#market-seeding-recommendations-body');
                var totalGap = 0;
                var totalCost = 0;
                var totalVolume = 0;

                $('#market-seeding-recommendations-error').addClass('d-none').text('');
                $body.empty();

                if (!attentionRecommendations.length) {
                    $('#market-seeding-recommendations-count').text('0');
                    $('#market-seeding-recommendations-gap').text('0');
                    $('#market-seeding-recommendations-cost').text(formatCurrency(0));
                    $('#market-seeding-recommendations-volume').text('0.00 m3');
                    $body.html('<div class="text-muted">No recommendations need attention for the current filters.</div>');
                    $('#market-seeding-apply-recommendations').prop('disabled', true);
                } else {
                    $.each(attentionRecommendations, function (index, item) {
                        var gap = Math.max(0, parseInt(item.recommended_quantity || 0, 10) - parseInt(item.current_target_quantity || 0, 10));
                        var deltaCost = parseFloat(item.recommendation_delta_cost || 0);
                        var deltaVolume = parseFloat(item.recommendation_delta_volume || 0);
                        totalGap += gap;
                        totalCost += isFinite(deltaCost) ? deltaCost : 0;
                        totalVolume += isFinite(deltaVolume) ? deltaVolume : 0;
                        $body.append(
                            '<div class="recommendation-card">' +
                                '<div class="recommendation-card-main">' +
                                    '<div>' +
                                        '<span class="recommendation-item-name">' + escapeHtml(item.type_name) + '</span>' +
                                        '<span class="recommendation-meta">' + escapeHtml(item.type_category || '-') + '</span>' +
                                        '<span class="recommendation-meta">' + escapeHtml(item.market_name || '-') + ' - ' + escapeHtml(item.location_name || '-') + '</span>' +
                                    '</div>' +
                                    '<div class="recommendation-target-change">' +
                                        '<span class="recommendation-current">' + numberWithCommas(item.current_target_quantity) + '</span>' +
                                        '<span class="recommendation-arrow">&rarr;</span>' +
                                        '<span class="recommendation-new">' + numberWithCommas(item.recommended_quantity) + '</span>' +
                                    '</div>' +
                                '</div>' +
                                '<div class="recommendation-delta-grid">' +
                                    '<div class="recommendation-delta">' +
                                        '<span class="recommendation-delta-label">Target</span>' +
                                        '<span class="recommendation-delta-value">+' + numberWithCommas(gap) + '</span>' +
                                    '</div>' +
                                    '<div class="recommendation-delta">' +
                                        '<span class="recommendation-delta-label">&Delta; Cost</span>' +
                                        '<span class="recommendation-delta-value">' + escapeHtml(formatCurrency(deltaCost)) + '</span>' +
                                    '</div>' +
                                    '<div class="recommendation-delta">' +
                                        '<span class="recommendation-delta-label">&Delta; Volume</span>' +
                                        '<span class="recommendation-delta-value">' + escapeHtml(formatDecimal(deltaVolume, 2)) + ' m3</span>' +
                                    '</div>' +
                                '</div>' +
                            '</div>'
                        );
                    });
                    $('#market-seeding-recommendations-count').text(numberWithCommas(attentionRecommendations.length));
                    $('#market-seeding-recommendations-gap').text(numberWithCommas(totalGap));
                    $('#market-seeding-recommendations-cost').text(formatCurrency(totalCost));
                    $('#market-seeding-recommendations-volume').text(formatDecimal(totalVolume, 2) + ' m3');
                    $('#market-seeding-apply-recommendations').prop('disabled', false);
                }

                $('#market-seeding-recommendations-modal').modal('show');
            });

            $('#market-seeding-apply-recommendations').on('click', function () {
                var $button = $(this);
                var payload = $.extend({}, recommendationFilters, {
                    item_ids: attentionRecommendations.map(function (item) {
                        return item.item_id;
                    })
                });

                $button.prop('disabled', true).text('Applying...');
                $('#market-seeding-recommendations-error').addClass('d-none').text('');

                $.ajax({
                    url: recommendationApplyUrl,
                    method: 'POST',
                    data: payload,
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                }).done(function (response) {
                    var message = response.message || 'Recommendations applied.';

                    if (response.errors && response.errors.length) {
                        message += ' ' + response.errors.join(' ');
                    }

                    $('#market-seeding-recommendations-error')
                        .removeClass('d-none alert-danger')
                        .addClass(response.errors && response.errors.length ? 'alert-warning' : 'alert-success')
                        .text(message);

                    window.setTimeout(function () {
                        window.location.reload();
                    }, 900);
                }).fail(function (xhr) {
                    var message = 'Unable to apply recommendations.';

                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }

                    $('#market-seeding-recommendations-error')
                        .removeClass('d-none alert-success alert-warning')
                        .addClass('alert-danger')
                        .text(message);
                    $button.prop('disabled', false).text('Apply Recommendations');
                });
            });

            $(document).on('click', '.market-seeding-edit-target', function () {
                var $button = $(this);

                $('#market-seeding-edit-target-form').attr('action', $button.data('update-url'));
                $('#market-seeding-edit-target-item').text($button.data('item-name'));
                $('#market-seeding-edit-target-market').text($button.data('market-name'));
                $('#market-seeding-edit-target-quantity').val($button.data('desired-quantity'));
                $('#market-seeding-edit-warning-quantity').val($button.data('warning-quantity'));
                $('#market-seeding-edit-target-recommended-value').text(numberWithCommas($button.data('recommended-quantity')));
                $('#market-seeding-edit-target-recommended-reason').text($button.data('recommendation-reason') || '');
                $('#market-seeding-use-recommended-target').data('recommended-quantity', $button.data('recommended-quantity'));
                $('#market-seeding-edit-target-success').addClass('d-none').text('');
                $('#market-seeding-edit-target-error').addClass('d-none').text('');
                $('#market-seeding-edit-target-form').data('trigger-url', $button.data('update-url'));
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
                updateTargetDetailProjection();
            });

            $('#market-seeding-edit-target-form').on('submit', function (event) {
                event.preventDefault();

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
                        .text(response.message || 'Target stock updated.');
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

            function formatMoney(value) {
                value = parseFloat(value);

                if (!isFinite(value) || value <= 0) {
                    return '-';
                }

                return '$' + value.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
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

                return (value > 0 ? '+' : '-') + formatCurrency(Math.abs(value));
            }

            function formatSignedVolume(value) {
                value = parseFloat(value || 0);

                if (!isFinite(value) || value === 0) {
                    return 'No change';
                }

                return (value > 0 ? '+' : '-') + formatDecimal(Math.abs(value), 2) + ' m3';
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
                if (targetTrendChart) {
                    targetTrendChart.destroy();
                    targetTrendChart = null;
                }
            }

            function renderTargetDetails(details) {
                details = details || {};
                currentTargetDetails = $.extend({}, details);

                $('#market-seeding-detail-current').text(numberWithCommas(details.current_quantity));
                $('#market-seeding-detail-local-price').text(formatMoney(details.local_price));
                $('#market-seeding-detail-jita-price').text(formatMoney(details.jita_price));
                $('#market-seeding-detail-seeded-value').text(formatMoney(details.seeded_value));
                $('#market-seeding-detail-item-volume').text(formatDecimal(details.item_volume, 2) + ' m3 each, packaged');
                updateTargetDetailProjection();

                if (details.price_delta === null || typeof details.price_delta === 'undefined') {
                    $('#market-seeding-detail-price-delta').text('No Jita comparison');
                } else {
                    var delta = parseFloat(details.price_delta);
                    var prefix = delta > 0 ? '+' : '';

                    $('#market-seeding-detail-price-delta').text(prefix + formatDecimal(delta, 1) + '% vs Jita');
                }
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

                $body.html('<tr><td colspan="5" class="text-muted">Loading transition history...</td></tr>');

                if (!url) {
                    renderTargetDetails({});
                    renderTargetTrend({});
                    $body.html('<tr><td colspan="5" class="text-muted">No transition history is available for this item.</td></tr>');
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

                    renderTargetDetails(response.details || {});
                    renderTargetTrend(response.trend || {});

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
                    renderTargetDetails({});
                    renderTargetTrend({});
                    $body.html('<tr><td colspan="5" class="text-danger">Unable to load transition history.</td></tr>');
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

            function escapeHtml(value) {
                return $('<div>').text(value || '').html();
            }
        });
    </script>
@endpush
