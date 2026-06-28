@extends('web::layouts.grids.12')

@section('title', 'Market Seeding Settings')
@section('page_header', 'Market Seeding Settings')

@section('content')
    @php
        $activeSkin = setting('skin') ?: 'default';
        $marketSeedingThemeClass = in_array($activeSkin, ['jet', 'iuligigi', 'gigigraphite'], true)
            ? 'market-seeding-dark-skin'
            : '';
    @endphp

    <style>
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
        .market-seeding-card .card-tools {
            display: flex;
            flex: 0 0 auto;
            flex-wrap: wrap;
            gap: .35rem;
            justify-content: flex-end;
            margin-left: auto;
        }
        .market-seeding-subsection {
            border-top: 1px solid #e9ecef;
            margin-top: 1rem;
            padding-top: 1rem;
        }
        .market-seeding-add-toolbar {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            justify-content: space-between;
        }
        .market-seeding-add-toolbar .text-muted {
            flex: 1 1 auto;
            min-width: 220px;
        }
        .market-seeding-add-toolbar .btn-group form {
            display: inline-flex;
        }
        .market-seeding-settings-shell .btn-group form {
            display: inline-flex;
        }
        .market-seeding-doctrine-summary {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            margin-top: .75rem;
        }
        .market-seeding-doctrine-pill {
            border: 1px solid #e9ecef;
            border-radius: .25rem;
            padding: .45rem .6rem;
        }
        .market-seeding-doctrine-pill strong {
            display: block;
            line-height: 1.2;
        }
        .market-seeding-doctrine-fit-summary {
            display: grid;
            gap: .35rem;
            margin-top: .55rem;
            min-width: 260px;
        }
        .market-seeding-doctrine-fit-summary-row {
            align-items: center;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: .25rem;
            display: flex;
            gap: .75rem;
            justify-content: space-between;
            padding: .35rem .45rem;
        }
        .market-seeding-doctrine-fit-summary-name {
            min-width: 0;
        }
        .market-seeding-doctrine-fit-summary-name strong {
            display: inline;
            font-size: .9rem;
        }
        .market-seeding-doctrine-fit-summary-name span {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .market-seeding-doctrine-fit-summary-badges {
            display: inline-flex;
            flex: 0 0 auto;
            gap: .25rem;
        }
        .market-seeding-doctrine-fit-summary-badges .badge {
            font-weight: 600;
        }
        .market-seeding-profile-list {
            display: grid;
            gap: .75rem;
        }
        .market-seeding-profile-row {
            border: 1px solid #e9ecef;
            border-radius: .25rem;
            padding: .75rem;
        }
        .market-seeding-profile-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
            justify-content: flex-end;
        }
        .market-seeding-profile-loader {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: .25rem;
            margin-bottom: .75rem;
            padding: .75rem;
        }
        .market-seeding-profile-modal .modal-body {
            max-height: 75vh;
            overflow-y: auto;
        }
        .market-seeding-add-modal .modal-body {
            max-height: 75vh;
            overflow-y: auto;
        }
        .market-seeding-add-modal .nav-tabs {
            margin-bottom: 1rem;
        }
        .market-seeding-dark-skin .text-muted {
            color: #b8c7ce !important;
        }
        .market-seeding-dark-skin .market-seeding-subsection {
            border-top-color: #3c4b54;
        }
        .market-seeding-dark-skin .alert-light {
            background: #1f2d3d;
            border-color: #3c4b54 !important;
            color: #e9ecef;
        }
        .market-seeding-dark-skin .market-seeding-profile-row,
        .market-seeding-dark-skin .market-seeding-profile-loader,
        .market-seeding-dark-skin .market-seeding-doctrine-pill {
            background: #1f2d3d;
            border-color: #3c4b54;
            color: #e9ecef;
        }
        .market-seeding-dark-skin .market-seeding-doctrine-fit-summary-row {
            background: #222d32;
            border-color: #3c4b54;
        }
        .market-seeding-dark-skin.market-seeding-profile-modal .modal-content,
        .market-seeding-dark-skin .market-seeding-profile-modal .modal-content,
        .market-seeding-dark-skin.market-seeding-add-modal .modal-content,
        .market-seeding-dark-skin .market-seeding-add-modal .modal-content {
            background: #222d32;
            color: #e9ecef;
        }
        .market-seeding-dark-skin.market-seeding-profile-modal .modal-header,
        .market-seeding-dark-skin.market-seeding-profile-modal .modal-footer,
        .market-seeding-dark-skin .market-seeding-profile-modal .modal-header,
        .market-seeding-dark-skin .market-seeding-profile-modal .modal-footer,
        .market-seeding-dark-skin.market-seeding-add-modal .modal-header,
        .market-seeding-dark-skin.market-seeding-add-modal .modal-footer,
        .market-seeding-dark-skin .market-seeding-add-modal .modal-header,
        .market-seeding-dark-skin .market-seeding-add-modal .modal-footer {
            border-color: #3c4b54;
        }
        .market-seeding-dark-skin .market-seeding-add-modal .nav-tabs {
            border-bottom-color: #3c4b54;
        }
        .market-seeding-dark-skin .market-seeding-add-modal .nav-tabs .nav-link.active {
            background: #1f2d3d;
            border-color: #3c4b54 #3c4b54 #1f2d3d;
            color: #e9ecef;
        }
        .market-seeding-dark-skin .table {
            color: #e9ecef;
        }
        .market-seeding-dark-skin .table thead th,
        .market-seeding-dark-skin .table td {
            border-color: #3c4b54;
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
        .market-seeding-type-filter {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            justify-content: flex-end;
            margin-bottom: .5rem;
        }
        .market-seeding-type-filter .form-control {
            max-width: 260px;
        }
        .market-seeding-row-saved > td {
            animation: market-seeding-row-saved 1.8s ease-out;
        }
        .market-seeding-source-icons {
            display: inline-flex;
            gap: .25rem;
            vertical-align: middle;
        }
        .market-seeding-source-column {
            text-align: center;
            width: 74px;
        }
        .market-seeding-target-history-modal .modal-body {
            max-height: 70vh;
            overflow-y: auto;
        }
        .market-seeding-target-history-meta {
            color: #6c757d;
            display: block;
            font-size: .8rem;
            margin-top: .15rem;
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
        .market-seeding-validation-list {
            margin-bottom: 0;
            max-height: 180px;
            overflow-y: auto;
            padding-left: 1.25rem;
        }
        .market-seeding-validation-line {
            font-family: Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        }
        .market-seeding-fit-contents {
            max-height: 160px;
            overflow-y: auto;
        }
        .market-seeding-fit-panel {
            border: 1px solid #e9ecef;
            border-radius: .25rem;
            padding: .75rem;
        }
        .market-seeding-fit-ship {
            border-bottom: 1px solid #e9ecef;
            margin-bottom: .5rem;
            padding-bottom: .5rem;
        }
        .market-seeding-fit-slots {
            display: grid;
            gap: .55rem;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }
        .market-seeding-fit-slot-group {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: .25rem;
            padding: .5rem;
        }
        .market-seeding-fit-slot-group-title {
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .02em;
            margin-bottom: .35rem;
            text-transform: uppercase;
        }
        .market-seeding-fit-slot-row {
            align-items: center;
            display: flex;
            justify-content: space-between;
            gap: .75rem;
            line-height: 1.35;
        }
        .market-seeding-fit-slot-row + .market-seeding-fit-slot-row {
            margin-top: .25rem;
        }
        @keyframes market-seeding-row-saved {
            0% {
                background: rgba(40, 167, 69, .25);
            }
            100% {
                background: transparent;
            }
        }
        .market-seeding-dark-skin .market-seeding-row-saved > td {
            animation-name: market-seeding-row-saved-dark;
        }
        .market-seeding-dark-skin .market-seeding-source-manual {
            background: rgba(60, 141, 188, .28);
            color: #9fd3f2;
        }
        .market-seeding-dark-skin .market-seeding-source-doctrine {
            background: rgba(40, 167, 69, .28);
            color: #9be7ad;
        }
        .market-seeding-dark-skin.market-seeding-target-history-modal .modal-content,
        .market-seeding-dark-skin .market-seeding-target-history-modal .modal-content {
            background: #222d32;
            color: #e9ecef;
        }
        .market-seeding-dark-skin.market-seeding-target-history-modal .modal-header,
        .market-seeding-dark-skin.market-seeding-target-history-modal .modal-footer,
        .market-seeding-dark-skin .market-seeding-target-history-modal .modal-header,
        .market-seeding-dark-skin .market-seeding-target-history-modal .modal-footer {
            border-color: #3c4b54;
        }
        .market-seeding-dark-skin .market-seeding-target-history-meta {
            color: #b8c7ce;
        }
        @keyframes market-seeding-row-saved-dark {
            0% {
                background: rgba(40, 167, 69, .35);
            }
            100% {
                background: transparent;
            }
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
        .market-seeding-dark-skin .market-seeding-fit-panel,
        .market-seeding-dark-skin .market-seeding-fit-ship,
        .market-seeding-dark-skin .market-seeding-fit-slot-group {
            border-color: #3c4b54;
        }
        .market-seeding-dark-skin .market-seeding-fit-slot-group {
            background: #1f2d3d;
        }
    </style>

    <div class="market-seeding-settings-shell {{ $marketSeedingThemeClass }}">
    <div class="card mb-4 market-seeding-card">
        <div class="card-header">
            <h3 class="card-title mb-0">General Settings</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('market-seeding.settings.general') }}" method="POST">
                {{ csrf_field() }}
                <div class="form-row align-items-end">
                    <div class="form-group col-md-3">
                        <label for="history_retention_days">History retention</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="history_retention_days" id="history_retention_days" value="{{ $historyRetentionDays }}" min="1" max="3650" required>
                            <div class="input-group-append">
                                <span class="input-group-text">days</span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="jita_price_refresh_minutes">Jita price refresh</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="jita_price_refresh_minutes" id="jita_price_refresh_minutes" value="{{ $jitaPriceRefreshMinutes }}" min="5" max="10080" required>
                            <div class="input-group-append">
                                <span class="input-group-text">minutes</span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="recommendation_sales_days">Recommendation sales window</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="recommendation_sales_days" id="recommendation_sales_days" value="{{ $recommendationSalesDays }}" min="1" max="365" required>
                            <div class="input-group-append">
                                <span class="input-group-text">days</span>
                            </div>
                        </div>
                    </div>
                    <div class="form-group col-md-3">
                        <label for="recommendation_buffer_percentage">Recommendation buffer</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="recommendation_buffer_percentage" id="recommendation_buffer_percentage" value="{{ $recommendationBufferPercentage }}" min="0" max="500" required>
                            <div class="input-group-append">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </form>
            <div class="d-flex flex-wrap justify-content-between align-items-center mt-2">
                <small class="text-muted mr-3">Recommendations use average estimated sales over the configured window, plus the buffer percentage. Jita prices are cached between refreshes to avoid hundreds of duplicate ESI calls.</small>
                <div class="btn-group">
                    <form action="{{ route('market-seeding.settings.history.clear') }}" method="POST" onsubmit="return confirm('Clear stock movement history, snapshots, and daily summaries? Target audit history will be kept. This cannot be undone.');">
                        {{ csrf_field() }}
                        {{ method_field('DELETE') }}
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="fas fa-history"></i> Clear Stock History
                        </button>
                    </form>
                    <form action="{{ route('market-seeding.settings.audit-history.clear') }}" method="POST" onsubmit="return confirm('Clear target audit history? This removes the record of who changed target quantities and cannot be undone.');">
                        {{ csrf_field() }}
                        {{ method_field('DELETE') }}
                        <button type="submit" class="btn btn-warning btn-sm">
                            <i class="fas fa-clipboard-list"></i> Clear Audit History
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4 market-seeding-card">
        <div class="card-header">
            <div>
                <h3 class="card-title mb-0">Markets</h3>
                <small class="text-muted card-subtitle">Create markets, manage reusable profiles, or refresh all market orders.</small>
            </div>
            <div class="card-tools">
                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#market-seeding-add-market-modal">
                    <i class="fas fa-plus"></i> Add Market
                </button>
                <button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#market-seeding-profiles-modal">
                    <i class="fas fa-layer-group"></i> Manage Profiles
                </button>
                <form action="{{ route('market-seeding.markets.refresh-all') }}" method="POST">
                    {{ csrf_field() }}
                    <button type="submit" class="btn btn-info btn-sm">
                        <i class="fas fa-sync"></i> Refresh ESI
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade market-seeding-profile-modal {{ $marketSeedingThemeClass }}" id="market-seeding-add-market-modal" tabindex="-1" role="dialog" aria-labelledby="market-seeding-add-market-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="market-seeding-add-market-modal-label">Add Market</h5>
                        <small class="text-muted">Choose a station or known structure, then decide who can see it.</small>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
            <form action="{{ route('market-seeding.markets.store') }}" method="POST">
                {{ csrf_field() }}
                <div class="form-row">
                    <div class="form-group col-md-5">
                        <label for="name">Display Name</label>
                        <input type="text" class="form-control" name="name" id="name" placeholder="Home staging" required>
                    </div>
                    <div class="form-group col-md-7">
                        <label for="location_selector">Station or Structure</label>
                        <select class="form-control market-location-selector" id="location_selector" style="width: 100%;"></select>
                        <input type="hidden" name="location_id" id="location_id" required>
                        <input type="hidden" name="location_name" id="location_name" required>
                        <input type="hidden" name="region_id" id="region_id" value="10000002">
                        <input type="hidden" name="solar_system_id" id="solar_system_id">
                        <input type="hidden" name="is_structure" id="is_structure" value="0">
                    </div>
                    <div class="form-group col-md-5">
                        <label for="role_id">Visibility Role</label>
                        <select name="role_id" id="role_id" class="form-control">
                            <option value="">Public</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}">{{ $role->title }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-link p-0" data-toggle="collapse" data-target="#manual-location-fields" aria-expanded="false" aria-controls="manual-location-fields">
                        Manual location entry
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Market
                    </button>
                </div>

                <div class="collapse mt-3" id="manual-location-fields">
                    <div class="alert alert-light border mb-0">
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label for="manual_location_id">Location ID</label>
                                <input type="number" class="form-control manual-location-id" id="manual_location_id" data-target="#location_id">
                            </div>
                            <div class="form-group col-md-5">
                                <label for="manual_location_name">Location Name</label>
                                <input type="text" class="form-control manual-location-name" id="manual_location_name" data-target="#location_name">
                            </div>
                            <div class="form-group col-md-2">
                                <label for="manual_region_id">Region ID</label>
                                <input type="number" class="form-control manual-region-id" id="manual_region_id" data-target="#region_id" value="10000002">
                            </div>
                            <div class="form-group col-md-2">
                                <label for="manual_is_structure">Type</label>
                                <select class="form-control manual-is-structure" id="manual_is_structure" data-target="#is_structure">
                                    <option value="0">Station</option>
                                    <option value="1">Structure</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade market-seeding-profile-modal {{ $marketSeedingThemeClass }}" id="market-seeding-profiles-modal" tabindex="-1" role="dialog" aria-labelledby="market-seeding-profiles-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="market-seeding-profiles-modal-label">Market Profiles</h5>
                        <small class="text-muted">Reusable stock lists that can be loaded into any market bulk import box.</small>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('market-seeding.profiles.store') }}" method="POST" class="mb-4">
                        {{ csrf_field() }}
                        <h5>Create Profile</h5>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Name</label>
                                <input type="text" class="form-control" name="name" placeholder="Common ammo" required>
                            </div>
                            <div class="form-group col-md-8">
                                <label>Description</label>
                                <input type="text" class="form-control" name="description" placeholder="Optional note">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Stock List</label>
                            <textarea name="stock_list" class="form-control" rows="8" placeholder="Scourge Fury Heavy Missile x5000&#10;Nova Fury Heavy Missile x5000&#10;Mjolnir Fury Heavy Missile x5000" required></textarea>
                        </div>
                        <div class="text-right">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Profile
                            </button>
                        </div>
                    </form>

                    <h5>Saved Profiles</h5>
                    @if($profiles->isEmpty())
                        <p class="text-muted mb-0">No profiles yet. Create one with item names and quantities, then load it into a market bulk import box.</p>
                    @else
                        <div class="market-seeding-profile-list">
                            @foreach($profiles as $profile)
                                @php
                                    $profileCollapseId = 'market-seeding-profile-' . $profile->id;
                                @endphp
                                <div class="market-seeding-profile-row">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong>{{ $profile->name }}</strong>
                                            @if($profile->description)
                                                <div class="text-muted small">{{ $profile->description }}</div>
                                            @endif
                                        </div>
                                        <div class="market-seeding-profile-actions">
                                            <button type="button" class="btn btn-default btn-xs" data-toggle="collapse" data-target="#{{ $profileCollapseId }}" aria-expanded="false" aria-controls="{{ $profileCollapseId }}">
                                                Edit
                                            </button>
                                            <form action="{{ route('market-seeding.profiles.destroy', $profile->id) }}" method="POST" onsubmit="return confirm('Delete this market profile?');">
                                                {{ csrf_field() }}
                                                {{ method_field('DELETE') }}
                                                <button type="submit" class="btn btn-danger btn-xs">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="collapse mt-3" id="{{ $profileCollapseId }}">
                                        <form action="{{ route('market-seeding.profiles.update', $profile->id) }}" method="POST">
                                            {{ csrf_field() }}
                                            {{ method_field('PUT') }}
                                            <div class="form-row">
                                                <div class="form-group col-md-5">
                                                    <label>Name</label>
                                                    <input type="text" class="form-control" name="name" value="{{ $profile->name }}" required>
                                                </div>
                                                <div class="form-group col-md-7">
                                                    <label>Description</label>
                                                    <input type="text" class="form-control" name="description" value="{{ $profile->description }}">
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label>Stock List</label>
                                                <textarea name="stock_list" class="form-control" rows="8" required>{{ $profile->stock_list }}</textarea>
                                            </div>
                                            <div class="text-right">
                                                <button type="submit" class="btn btn-primary btn-sm">Save Profile</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @include('seat-market-seeding::partials.import-preview-modal')

    @foreach($markets as $market)
        @php
            $marketCollapseId = 'market-settings-body-' . $market->id;
            $manualCollapseId = 'manual-location-fields-' . $market->id;
        @endphp

        <div class="card mb-4 market-seeding-card" id="market-seeding-card-{{ $market->id }}">
            <div class="card-header">
                <div>
                    <h3 class="card-title mb-0">{{ $market->name }}</h3>
                    <small class="text-muted card-subtitle">
                        {{ $market->location_name }} &middot;
                        <span class="market-seeding-tracked-count" data-count="{{ $market->items->count() }}">{{ $market->items->count() }}</span>
                        tracked items
                        @if($market->last_refreshed_at)
                            &middot;
                            @php
                                $refreshBadge = [
                                    'success' => 'badge-success',
                                    'skipped' => 'badge-warning',
                                    'error' => 'badge-danger',
                                ][$market->last_refresh_status] ?? 'badge-secondary';
                            @endphp
                            <span class="badge {{ $refreshBadge }}">{{ ucfirst($market->last_refresh_status ?: 'unknown') }}</span>
                            refreshed {{ $market->last_refreshed_at->format('Y-m-d H:i') }}
                        @else
                            &middot; <span class="badge badge-secondary">Never refreshed</span>
                        @endif
                    </small>
                </div>
                <div class="card-tools">
                    <button type="button" class="btn btn-default btn-sm" data-toggle="collapse" data-target="#{{ $marketCollapseId }}" aria-expanded="false" aria-controls="{{ $marketCollapseId }}">
                        <i class="fas fa-sliders-h"></i> Configure
                    </button>
                    <form action="{{ route('market-seeding.markets.move', $market->id) }}" method="POST">
                        {{ csrf_field() }}
                        <input type="hidden" name="direction" value="up">
                        <button type="submit" class="btn btn-default btn-sm" title="Move up" {{ $loop->first ? 'disabled' : '' }}>
                            <i class="fas fa-arrow-up"></i>
                        </button>
                    </form>
                    <form action="{{ route('market-seeding.markets.move', $market->id) }}" method="POST">
                        {{ csrf_field() }}
                        <input type="hidden" name="direction" value="down">
                        <button type="submit" class="btn btn-default btn-sm" title="Move down" {{ $loop->last ? 'disabled' : '' }}>
                            <i class="fas fa-arrow-down"></i>
                        </button>
                    </form>
                    <form action="{{ route('market-seeding.markets.destroy', $market->id) }}" method="POST" onsubmit="return confirm('Delete this seeded market and all of its stock targets?');">
                        {{ csrf_field() }}
                        {{ method_field('DELETE') }}
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
            <div class="collapse" id="{{ $marketCollapseId }}">
                <div class="card-body">
                    <form action="{{ route('market-seeding.markets.update', $market->id) }}" method="POST" class="mb-3">
                        {{ csrf_field() }}
                        {{ method_field('PUT') }}
                        <div class="form-row align-items-end">
                            <div class="form-group col-lg-3 col-md-6">
                                <label>Name</label>
                                <input type="text" class="form-control" name="name" value="{{ $market->name }}" required>
                            </div>
                            <div class="form-group col-lg-4 col-md-6">
                                <label>Station or Structure</label>
                                <select class="form-control market-location-selector" style="width: 100%;" data-prefix="market-{{ $market->id }}">
                                    <option value="{{ $market->location_id }}" selected>{{ $market->location_name }}</option>
                                </select>
                                <input type="hidden" name="location_id" id="market-{{ $market->id }}-location_id" value="{{ $market->location_id }}" required>
                                <input type="hidden" name="location_name" id="market-{{ $market->id }}-location_name" value="{{ $market->location_name }}" required>
                                <input type="hidden" name="region_id" id="market-{{ $market->id }}-region_id" value="{{ $market->region_id }}">
                                <input type="hidden" name="solar_system_id" id="market-{{ $market->id }}-solar_system_id" value="{{ $market->solar_system_id }}">
                                <input type="hidden" name="is_structure" id="market-{{ $market->id }}-is_structure" value="{{ $market->is_structure ? 1 : 0 }}">
                            </div>
                            <div class="form-group col-lg-2 col-md-6">
                                <label>Visibility Role</label>
                                <select name="role_id" class="form-control">
                                    <option value="">Public</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}" {{ $market->role_id == $role->id ? 'selected' : '' }}>{{ $role->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-lg-1 col-md-12">
                                <button type="submit" class="btn btn-primary btn-block">Save</button>
                            </div>
                        </div>

                        <button type="button" class="btn btn-link p-0" data-toggle="collapse" data-target="#{{ $manualCollapseId }}" aria-expanded="false" aria-controls="{{ $manualCollapseId }}">
                            Manual location override
                        </button>
                        <div class="collapse mt-3" id="{{ $manualCollapseId }}">
                            <div class="alert alert-light border mb-0">
                                <div class="form-row">
                                    <div class="form-group col-md-3">
                                        <label>Location ID</label>
                                        <input type="number" class="form-control manual-location-id" data-target="#market-{{ $market->id }}-location_id" value="{{ $market->location_id }}">
                                    </div>
                                    <div class="form-group col-md-5">
                                        <label>Location Name</label>
                                        <input type="text" class="form-control manual-location-name" data-target="#market-{{ $market->id }}-location_name" value="{{ $market->location_name }}">
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label>Region ID</label>
                                        <input type="number" class="form-control manual-region-id" data-target="#market-{{ $market->id }}-region_id" value="{{ $market->region_id }}">
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label>Type</label>
                                        <select class="form-control manual-is-structure" data-target="#market-{{ $market->id }}-is_structure">
                                            <option value="0" {{ !$market->is_structure ? 'selected' : '' }}>Station</option>
                                            <option value="1" {{ $market->is_structure ? 'selected' : '' }}>Structure</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>

                    <div class="market-seeding-subsection market-seeding-add-toolbar">
                        <div class="text-muted">
                            Manage manual targets, bulk imports, saved fits, and tracked doctrines from one place.
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-success" data-toggle="modal" data-target="#market-add-modal-{{ $market->id }}">
                                <i class="fas fa-plus"></i> Add or Import
                            </button>
                            <form action="{{ route('market-seeding.items.clear-market', $market->id) }}" method="POST" onsubmit="return confirm({{ json_encode('Clear all tracked items for ' . $market->name . '? This also removes tracked doctrines for this market and cannot be undone.') }});">
                                {{ csrf_field() }}
                                {{ method_field('DELETE') }}
                                <button type="submit" class="btn btn-danger">
                                    <i class="fas fa-broom"></i> Clear Market Items
                                </button>
                            </form>
                        </div>
                    </div>

                    @if($seatFittingAvailable)
                        <div class="market-seeding-doctrine-summary-shell" data-market-id="{{ $market->id }}">
                            @include('seat-market-seeding::partials.tracked-doctrine-summary', ['market' => $market])
                        </div>
                    @endif

                    <div class="table-responsive market-seeding-subsection market-seeding-table-shell">
                        @php
                            $typeCategories = $market->items
                                ->map(fn ($item) => $item->typeCategoryName())
                                ->unique()
                                ->sort()
                                ->values();
                        @endphp
                        <div class="market-seeding-type-filter">
                            <label class="mb-0 text-muted" for="market-seeding-settings-type-filter-{{ $market->id }}">Category</label>
                            <select class="form-control form-control-sm market-seeding-settings-type-filter" id="market-seeding-settings-type-filter-{{ $market->id }}" data-table="#market-seeding-settings-table-{{ $market->id }}">
                                <option value="">All Categories</option>
                                @foreach($typeCategories as $typeCategory)
                                    <option value="{{ $typeCategory }}">{{ $typeCategory }}</option>
                                @endforeach
                            </select>
                        </div>
                        <table class="table table-sm table-hover market-seeding-settings-table" id="market-seeding-settings-table-{{ $market->id }}">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Category</th>
                                    <th class="market-seeding-source-column">Source</th>
                                    <th class="text-right">Target</th>
                                    <th class="text-right">Low Warning</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($market->items->sortBy('type_name') as $item)
                                    <tr data-item-id="{{ $item->id }}" data-category="{{ $item->typeCategoryName() }}">
                                        <td>{{ $item->type_name }}</td>
                                        <td>{{ $item->typeCategoryName() }}</td>
                                        <td class="market-seeding-source-column">
                                            @include('seat-market-seeding::partials.source-icons', ['sourceFlags' => $item->sourceFlags()])
                                        </td>
                                        <td class="text-right" style="width: 140px;" data-order="{{ $item->desired_quantity }}">
	                                            <form id="item-update-{{ $item->id }}" action="{{ route('market-seeding.items.update', $item->id) }}" method="POST" class="market-seeding-update-item-form" data-table="#market-seeding-settings-table-{{ $market->id }}" data-original-target-quantity="{{ (int) $item->desired_quantity }}" data-original-warning-quantity="{{ (int) $item->warning_quantity }}">
                                                {{ csrf_field() }}
                                                {{ method_field('PUT') }}
                                                <input type="number" class="form-control form-control-sm text-right" name="desired_quantity" value="{{ $item->desired_quantity }}" min="1">
                                            </form>
                                        </td>
                                        <td class="text-right" style="width: 140px;" data-order="{{ $item->warning_quantity }}">
                                            <input form="item-update-{{ $item->id }}" type="number" class="form-control form-control-sm text-right" name="warning_quantity" value="{{ $item->warning_quantity }}" min="0">
                                        </td>
                                        <td class="text-right" style="width: 200px;">
                                            <button type="button" class="btn btn-default btn-xs market-seeding-show-target-history" data-history-url="{{ route('market-seeding.items.history', $item->id) }}" data-item-name="{{ $item->type_name }}" title="Target history">
                                                <i class="fas fa-history"></i>
                                            </button>
                                            <button type="submit" class="btn btn-primary btn-xs market-seeding-save-item" form="item-update-{{ $item->id }}">Save</button>
                                            <form action="{{ route('market-seeding.items.destroy', $item->id) }}" method="POST" class="market-seeding-delete-item-form" data-table="#market-seeding-settings-table-{{ $market->id }}" style="display: inline-block;">
                                                {{ csrf_field() }}
                                                {{ method_field('DELETE') }}
                                                <button type="submit" class="btn btn-danger btn-xs">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @include('seat-market-seeding::partials.market-add-modal', ['market' => $market])
    @endforeach
    </div>

    <div class="modal fade market-seeding-target-history-modal {{ $marketSeedingThemeClass }}" id="market-seeding-target-history-modal" tabindex="-1" role="dialog" aria-labelledby="market-seeding-target-history-title" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="market-seeding-target-history-title">Target History</h5>
                        <span class="market-seeding-target-history-meta" id="market-seeding-target-history-item">Select an item to view target changes.</span>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>When</th>
                                    <th>Type</th>
                                    <th>Changed By</th>
                                    <th class="text-right">Target</th>
                                    <th class="text-right">Low Warning</th>
                                </tr>
                            </thead>
                            <tbody id="market-seeding-target-history-body">
                                <tr>
                                    <td colspan="5" class="text-muted">No item selected.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('javascript')
    <script>
        $(function () {
            var csrfToken = @json(csrf_token());
            var marketProfiles = @json($profiles->mapWithKeys(function ($profile) {
                return [$profile->id => $profile->stock_list];
            }));
            var settingsTables = null;
            var previewImportForm = null;
            var previewSourceModal = null;
            var previewApplying = false;
            var doctrinePreviewTimer = null;

            if ($.fn.DataTable) {
                settingsTables = $('.market-seeding-settings-table').DataTable({
                    order: [[0, 'asc']],
                    paging: true,
                    pageLength: 25,
                    lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
                    stateSave: true,
                    autoWidth: false,
                    columnDefs: [
                        { orderable: false, searchable: false, targets: [2, 5] }
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

            $('.market-seeding-card .collapse').on('shown.bs.collapse', function () {
                if (settingsTables) {
                    settingsTables.columns.adjust();
                }
            });

            $('.market-seeding-settings-type-filter').on('change', function () {
                var tableSelector = $(this).data('table');
                var value = $(this).val();

                if ($.fn.DataTable && $.fn.DataTable.isDataTable(tableSelector)) {
                    $(tableSelector).DataTable()
                        .column(1)
                        .search(value ? '^' + escapeRegex(value) + '$' : '', true, false)
                        .draw();
                }
            });

            $('.market-seeding-add-item-form').on('submit', function (event) {
                event.preventDefault();

                var $form = $(this);
                var $button = $form.find('button[type="submit"]');
                var $feedback = $form.find('.market-seeding-add-feedback');

                $button.prop('disabled', true);
                $feedback.hide().removeClass('text-success text-danger').text('');

                $.ajax({
                    url: $form.attr('action'),
                    method: 'POST',
                    data: serializeInlineItemForm($form),
                    headers: {
                        Accept: 'application/json'
                    }
                }).done(function (response) {
                    upsertItemRow($form.data('table'), response.item);

                    if (response.created) {
                        updateTrackedCount(marketCardForForm($form), null, 1);
                    }

                    $form.find('select[name="type_id"]').val(null).trigger('change');
                    $form.find('input[name="desired_quantity"]').val('1');
                    $form.find('input[name="warning_percentage"]').val('33');
                    $feedback.addClass('text-success').text(response.message || 'Item saved successfully.').show();
                }).fail(function (xhr) {
                    $feedback.addClass('text-danger').text(errorMessage(xhr)).show();
                }).always(function () {
                    $button.prop('disabled', false);
                });
            });

            $('.market-seeding-import-form').on('submit', function (event) {
                event.preventDefault();

                var $form = $(this);
                var $button = $form.find('button[type="submit"]');
                var $feedback = $form.find('.market-seeding-import-feedback');

                $button.prop('disabled', true);
                $feedback.hide().removeClass('text-success text-danger').text('');

                $.ajax({
                    url: $form.attr('action'),
                    method: 'POST',
                    data: serializeInlineItemForm($form),
                    headers: {
                        Accept: 'application/json'
                    }
                }).done(function (response) {
                    replaceItemRows($form.data('table'), response.items || []);
                    updateTrackedCount(marketCardForForm($form), response.tracked_count);
                    resetImportForm($form);
                    showSettingsNotice(response.message || 'Import completed successfully.', 'success');
                    $feedback.addClass('text-success').text(response.message || 'Import completed successfully.').show();
                }).fail(function (xhr) {
                    $feedback.addClass('text-danger').text(errorMessage(xhr)).show();
                }).always(function () {
                    $button.prop('disabled', false);
                });
            });

            $('.market-seeding-preview-import').on('click', function () {
                var $form = $(this).closest('.market-seeding-import-form');
                var $button = $(this);
                var $feedback = $form.find('.market-seeding-import-feedback');
                var $sourceModal = $form.closest('.modal');

                $button.prop('disabled', true);
                $feedback.hide().removeClass('text-success text-danger').text('');

                $.ajax({
                    url: $form.data('preview-url'),
                    method: 'POST',
                    data: $form.serialize(),
                    headers: {
                        Accept: 'application/json'
                    }
                }).done(function (response) {
                    previewImportForm = $form;
                    renderImportPreview(response);
                    showImportPreviewModal($sourceModal);
                }).fail(function (xhr) {
                    $feedback.addClass('text-danger').text(errorMessage(xhr)).show();
                }).always(function () {
                    $button.prop('disabled', false);
                });
            });

            $('.market-seeding-preview-doctrine').on('click', function () {
                var $form = $(this).closest('.market-seeding-tracked-doctrine-form');
                var $button = $(this);
                var $feedback = doctrineFeedbackForForm($form);
                var $sourceModal = $form.closest('.modal');

                fetchDoctrinePreview($form, $button, $feedback, $sourceModal, true);
            });

            $(document).on('click', '.market-seeding-edit-tracked-doctrine', function () {
                var formId = $(this).attr('form');
                var $form = $('#' + formId);
                var $feedback = doctrineFeedbackForForm($form);
                var $sourceModal = $form.closest('.modal');

                fetchDoctrinePreview($form, $(this), $feedback, $sourceModal, true);
            });

            $(document).on('input change', '.market-seeding-fit-ship-multiplier, .market-seeding-fit-fitting-multiplier', function () {
                if (!previewImportForm || !previewImportForm.hasClass('market-seeding-tracked-doctrine-form')) {
                    return;
                }

                writeDoctrineFitSettingsToForm(previewImportForm);
                $('.market-seeding-doctrine-preview-refresh-status').text('Updating preview...');

                window.clearTimeout(doctrinePreviewTimer);
                doctrinePreviewTimer = window.setTimeout(function () {
                    fetchDoctrinePreview(previewImportForm, $(), doctrineFeedbackForForm(previewImportForm), $(), false, false);
                }, 450);
            });

            $('.market-seeding-run-previewed-import').on('click', function () {
                if (!previewImportForm) {
                    return;
                }

                if (previewImportForm.hasClass('market-seeding-tracked-doctrine-form')) {
                    writeDoctrineFitSettingsToForm(previewImportForm);
                }

                previewApplying = true;
                previewSourceModal = null;
                $('#market-seeding-import-preview-modal').modal('hide');
                previewImportForm.trigger('submit');
            });

            $('#market-seeding-import-preview-modal').on('hidden.bs.modal', function () {
                if (previewSourceModal && previewSourceModal.length && !previewApplying) {
                    previewSourceModal.modal('show');
                }

                previewApplying = false;
                previewSourceModal = null;
            });

            $(document).on('submit', '.market-seeding-tracked-doctrine-form', function (event) {
                event.preventDefault();

                var $form = $(this);
                var $button = trackedDoctrineSubmitButton($form);
                var $feedback = doctrineFeedbackForForm($form);
                var originalButtonHtml = $button.html();

                $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving');
                $feedback.hide().removeClass('text-success text-danger').text('');

                $.ajax({
                    url: $form.attr('action'),
                    method: 'POST',
                    data: serializeInlineItemForm($form),
                    headers: {
                        Accept: 'application/json'
                    }
                }).done(function (response) {
                    updateDoctrineUi($form.data('market-id'), response);
                    replaceItemRows('#market-seeding-settings-table-' + $form.data('market-id'), response.items || []);
                    updateTrackedCount($('#market-seeding-card-' + $form.data('market-id')), response.tracked_count);
                    $form.find('.doctrine-selector').val(null).trigger('change');
                    $form.find('.market-seeding-doctrine-fit-settings').val('');
                    $form.find('input[name="multiplier"]').val('10');
                    $form.find('input[name="warning_percentage"]').val('33');
                    $form.find('select[name="merge_mode"]').val('max');
                    $form.find('select[name="fit_aggregation_mode"]').val('max');
                    $feedback.addClass('text-success').text(response.message || 'Doctrine tracking updated successfully.').show();
                }).fail(function (xhr) {
                    $feedback.addClass('text-danger').text(errorMessage(xhr)).show();
                }).always(function () {
                    $button.prop('disabled', false).html(originalButtonHtml || 'Save');
                });
            });

            $(document).on('submit', '.market-seeding-delete-tracked-doctrine-form', function (event) {
                event.preventDefault();

                if (!confirm('Stop tracking this doctrine for this market?')) {
                    return;
                }

                var $form = $(this);
                var $button = $form.find('button[type="submit"]');
                var originalButtonHtml = $button.html();

                $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                $.ajax({
                    url: $form.attr('action'),
                    method: 'POST',
                    data: serializeInlineItemForm($form),
                    headers: {
                        Accept: 'application/json'
                    }
                }).done(function (response) {
                    updateDoctrineUi($form.data('market-id'), response);
                    replaceItemRows('#market-seeding-settings-table-' + $form.data('market-id'), response.items || []);
                    updateTrackedCount($('#market-seeding-card-' + $form.data('market-id')), response.tracked_count);
                }).fail(function (xhr) {
                    alert(errorMessage(xhr));
                    $button.prop('disabled', false).html(originalButtonHtml);
                });
            });

	            $(document).on('submit', '.market-seeding-update-item-form', function (event) {
                event.preventDefault();

                var $form = $(this);
                var $button = $('[form="' + $form.attr('id') + '"].market-seeding-save-item');
                var originalButtonHtml = $button.html();

                $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving');

                $.ajax({
                    url: $form.attr('action'),
                    method: 'POST',
                    data: $form.serialize(),
                    headers: {
                        Accept: 'application/json'
                    }
                }).done(function (response) {
                    upsertItemRow($form.data('table'), response.item);
                    markItemRowSaved($form.data('table'), response.item.id);
                    showButtonSuccess(
                        rowForItem($form.data('table'), response.item.id).find('.market-seeding-save-item'),
                        originalButtonHtml,
                        'Saved'
                    );
                }).fail(function (xhr) {
                    alert(errorMessage(xhr));
                    $button.prop('disabled', false).html(originalButtonHtml);
                }).always(function () {
                    if (!$button.data('restore-pending')) {
                        $button.prop('disabled', false).html(originalButtonHtml);
                    }
	                });
	            });

	            $(document).on('input change', '.market-seeding-update-item-form input[name="desired_quantity"]', function () {
	                var $form = $(this).closest('form');
	                scaleSettingsWarningFromTarget($form);
	            });

	            $(document).on('input change', 'input[name="warning_quantity"]', function () {
	                var $input = $(this);
	                var $form = $('#' + $input.attr('form'));
	                clampSettingsWarningToTarget($form);
	            });

            $(document).on('submit', '.market-seeding-delete-item-form', function (event) {
                event.preventDefault();

                if (!confirm('Remove this item?')) {
                    return;
                }

                var $form = $(this);
                var $button = $form.find('button[type="submit"]');
                var $card = $form.closest('.market-seeding-card');
                var originalButtonHtml = $button.html();

                $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                $.ajax({
                    url: $form.attr('action'),
                    method: 'POST',
                    data: $form.serialize(),
                    headers: {
                        Accept: 'application/json'
                    }
                }).done(function (response) {
                    if (response.item) {
                        upsertItemRow($form.data('table'), response.item);
                        markItemRowSaved($form.data('table'), response.item.id);
                    } else {
                        removeItemRow($form.data('table'), response.item_id);
                    }

                    if (typeof response.tracked_count === 'number') {
                        updateTrackedCount($card, response.tracked_count);
                    } else {
                        updateTrackedCount($card, null, -1);
                    }
                }).fail(function (xhr) {
                    alert(errorMessage(xhr));
                    $button.prop('disabled', false).html(originalButtonHtml);
                });
            });

            $(document).on('click', '.market-seeding-show-target-history', function () {
                var $button = $(this);
                var $body = $('#market-seeding-target-history-body');

                $('#market-seeding-target-history-item').text($button.data('item-name') || 'Target changes');
                $body.html('<tr><td colspan="5" class="text-muted">Loading target history...</td></tr>');
                $('#market-seeding-target-history-modal').modal('show');

                $.ajax({
                    url: $button.data('history-url'),
                    method: 'GET',
                    headers: {
                        Accept: 'application/json'
                    }
                }).done(function (response) {
                    renderTargetHistoryRows($body, response.target_history || []);
                }).fail(function () {
                    $body.html('<tr><td colspan="5" class="text-danger">Unable to load target history.</td></tr>');
                });
            });

            $('.market-seeding-load-profile').on('click', function () {
                var $form = $(this).closest('.market-seeding-import-form');
                var profileId = $form.find('.market-seeding-profile-selector').val();
                var profileText = marketProfiles[profileId] || '';
                var $textarea = $form.find('.market-seeding-stock-list');

                if (!profileText) {
                    return;
                }

                if ($.trim($textarea.val()) !== '' && !confirm('Replace the current bulk import text with this profile?')) {
                    return;
                }

                $textarea.val(profileText).trigger('focus');
            });

            $('.item-selector').each(function () {
                $(this).select2({
                    dropdownParent: $(this).closest('.modal').length ? $(this).closest('.modal') : $(document.body),
                    ajax: {
                        url: '{{ route('market-seeding.search.items') }}',
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return { q: params.term };
                        },
                        processResults: function (data) {
                            return data;
                        }
                    },
                    minimumInputLength: 2,
                    placeholder: 'Search item name'
                });
            });

            $('.saved-fitting-selector').each(function () {
                $(this).select2({
                    dropdownParent: $(this).closest('.modal').length ? $(this).closest('.modal') : $(document.body),
                    ajax: {
                        url: '{{ route('market-seeding.search.saved-fittings') }}',
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return { q: params.term };
                        },
                        processResults: function (data) {
                            return data;
                        }
                    },
                    minimumInputLength: 2,
                    placeholder: 'Search saved fit or doctrine'
                });
            });

            $('.doctrine-selector').each(function () {
                var $selector = $(this);
                var marketId = $selector.closest('.market-seeding-tracked-doctrine-form').data('market-id');

                $selector.select2({
                    dropdownParent: $selector.closest('.modal').length ? $selector.closest('.modal') : $(document.body),
                    ajax: {
                        url: '{{ route('market-seeding.search.doctrines') }}',
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return {
                                q: params.term,
                                market_id: marketId || ''
                            };
                        },
                        processResults: function (data) {
                            return data;
                        }
                    },
                    minimumInputLength: 2,
                    placeholder: 'Search Seat-Fitting doctrine'
                });
            });

            $('.market-location-selector').each(function () {
                $(this).select2({
                    dropdownParent: $(this).closest('.modal').length ? $(this).closest('.modal') : $(document.body),
                    ajax: {
                        url: '{{ route('market-seeding.search.locations') }}',
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            return { q: params.term };
                        },
                        processResults: function (data) {
                            return data;
                        }
                    },
                    minimumInputLength: 3,
                    placeholder: 'Search station or known structure'
                });
            }).on('select2:select', function (event) {
                var data = event.params.data;
                var prefix = $(this).data('prefix');
                var selectors = prefix ? {
                    locationId: '#' + prefix + '-location_id',
                    locationName: '#' + prefix + '-location_name',
                    regionId: '#' + prefix + '-region_id',
                    solarSystemId: '#' + prefix + '-solar_system_id',
                    isStructure: '#' + prefix + '-is_structure',
                } : {
                    locationId: '#location_id',
                    locationName: '#location_name',
                    regionId: '#region_id',
                    solarSystemId: '#solar_system_id',
                    isStructure: '#is_structure',
                };

                $(selectors.locationId).val(data.id);
                $(selectors.locationName).val(data.text);
                $(selectors.regionId).val(data.region_id || 10000002);
                $(selectors.solarSystemId).val(data.solar_system_id || '');
                $(selectors.isStructure).val(data.is_structure ? 1 : 0);
            });

            $('.manual-location-id, .manual-location-name, .manual-region-id, .manual-is-structure').on('input change', function () {
                var target = $(this).data('target');
                $(target).val($(this).val());
            });

            function upsertItemRow(tableSelector, item) {
                var row = $(itemRowHtml(item, tableSelector));
                ensureSettingsTypeFilterOption(tableSelector, item.type_category || 'Unknown');

                if ($.fn.DataTable && $.fn.DataTable.isDataTable(tableSelector)) {
                    var table = $(tableSelector).DataTable();
                    var existingRow = null;

                    table.rows().every(function () {
                        if ($(this.node()).data('item-id') == item.id) {
                            existingRow = this;
                        }
                    });

                    if (existingRow) {
                        existingRow.remove();
                    }

                    table.row.add(row[0]).draw(false);
                    return;
                }

                var existing = $(tableSelector).find('tbody tr[data-item-id="' + item.id + '"]');
                if (existing.length) {
                    existing.replaceWith(row);
                } else {
                    $(tableSelector).find('tbody').append(row);
                }
            }

            function replaceItemRows(tableSelector, items) {
                rebuildSettingsTypeFilter(tableSelector, items);

                if ($.fn.DataTable && $.fn.DataTable.isDataTable(tableSelector)) {
                    var table = $(tableSelector).DataTable();
                    table.clear();
                    $.each(items, function (index, item) {
                        table.row.add($(itemRowHtml(item, tableSelector))[0]);
                    });
                    table.draw(false);
                    return;
                }

                var rows = $.map(items, function (item) {
                    return itemRowHtml(item, tableSelector);
                });
                $(tableSelector).find('tbody').html(rows.join(''));
            }

            function settingsTypeFilterForTable(tableSelector) {
                return $('.market-seeding-settings-type-filter[data-table="' + tableSelector + '"]');
            }

            function ensureSettingsTypeFilterOption(tableSelector, typeGroup) {
                var $filter = settingsTypeFilterForTable(tableSelector);

                if (!$filter.length || !typeGroup) {
                    return;
                }

                if ($filter.find('option').filter(function () { return $(this).val() === typeGroup; }).length) {
                    return;
                }

                $filter.append($('<option>', {
                    value: typeGroup,
                    text: typeGroup
                }));
                sortSelectOptions($filter);
            }

            function rebuildSettingsTypeFilter(tableSelector, items) {
                var $filter = settingsTypeFilterForTable(tableSelector);
                var currentValue = $filter.val();
                var typeCategories = {};

                if (!$filter.length) {
                    return;
                }

                $.each(items || [], function (index, item) {
                    typeCategories[item.type_category || 'Unknown'] = true;
                });

                $filter.find('option:not([value=""])').remove();
                $.each(Object.keys(typeCategories).sort(), function (index, typeGroup) {
                    $filter.append($('<option>', {
                        value: typeGroup,
                        text: typeGroup
                    }));
                });

                $filter.val(typeCategories[currentValue] ? currentValue : '');
            }

            function sortSelectOptions($select) {
                var options = $select.find('option:not([value=""])').get();

                options.sort(function (a, b) {
                    return $(a).text().localeCompare($(b).text());
                });

                $.each(options, function (index, option) {
                    $select.append(option);
                });
            }

            function removeItemRow(tableSelector, itemId) {
                if ($.fn.DataTable && $.fn.DataTable.isDataTable(tableSelector)) {
                    var table = $(tableSelector).DataTable();

                    table.rows().every(function () {
                        if ($(this.node()).data('item-id') == itemId) {
                            this.remove();
                        }
                    });

                    table.draw(false);
                    return;
                }

                $(tableSelector).find('tbody tr[data-item-id="' + itemId + '"]').remove();
            }

            function markItemRowSaved(tableSelector, itemId) {
                var $row = rowForItem(tableSelector, itemId);

                $row.removeClass('market-seeding-row-saved');

                window.setTimeout(function () {
                    $row.addClass('market-seeding-row-saved');
                }, 10);

                window.setTimeout(function () {
                    $row.removeClass('market-seeding-row-saved');
                }, 1900);
            }

	            function rowForItem(tableSelector, itemId) {
	                if ($.fn.DataTable && $.fn.DataTable.isDataTable(tableSelector)) {
	                    var table = $(tableSelector).DataTable();
                    var row = $();

                    table.rows().every(function () {
                        if ($(this.node()).data('item-id') == itemId) {
                            row = $(this.node());
                        }
                    });

                    return row;
                }
	
	                return $(tableSelector).find('tbody tr[data-item-id="' + itemId + '"]');
	            }

	            function scaleSettingsWarningFromTarget($form) {
	                var targetQuantity = Math.max(1, parseInt($form.find('input[name="desired_quantity"]').val() || 1, 10));
	                var originalTarget = Math.max(1, parseInt($form.data('original-target-quantity') || 1, 10));
	                var originalWarning = Math.max(0, parseInt($form.data('original-warning-quantity') || 0, 10));
	                var warningQuantity = originalWarning === 0
	                    ? 0
	                    : Math.ceil(targetQuantity * (originalWarning / originalTarget));

	                warningQuantity = Math.min(targetQuantity, Math.max(0, warningQuantity));
	                $('input[name="warning_quantity"][form="' + $form.attr('id') + '"]').val(warningQuantity);
	            }

	            function clampSettingsWarningToTarget($form) {
	                if (!$form.length) {
	                    return;
	                }

	                var targetQuantity = Math.max(1, parseInt($form.find('input[name="desired_quantity"]').val() || 1, 10));
	                var $warning = $('input[name="warning_quantity"][form="' + $form.attr('id') + '"]');
	                var warningQuantity = Math.max(0, parseInt($warning.val() || 0, 10));

	                if (warningQuantity > targetQuantity) {
	                    $warning.val(targetQuantity);
	                }
	            }

            function showButtonSuccess($button, originalButtonHtml, label) {
                $button
                    .data('restore-pending', true)
                    .removeClass('btn-primary')
                    .addClass('btn-success')
                    .prop('disabled', true)
                    .html('<i class="fas fa-check"></i> ' + escapeHtml(label));

                window.setTimeout(function () {
                    $button
                        .data('restore-pending', false)
                        .removeClass('btn-success')
                        .addClass('btn-primary')
                        .prop('disabled', false)
                        .html(originalButtonHtml);
                }, 1200);
            }

            function renderTargetHistoryRows($body, rows) {
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
                var oldLabel = oldValue === null || typeof oldValue === 'undefined' ? '-' : formatWhole(oldValue);
                var newLabel = newValue === null || typeof newValue === 'undefined' ? '-' : formatWhole(newValue);

                return escapeHtml(oldLabel) + ' &rarr; ' + escapeHtml(newLabel);
            }

            function marketCardForForm($form) {
                var cardSelector = $form.data('card');

                return cardSelector ? $(cardSelector) : $form.closest('.market-seeding-card');
            }

            function doctrineFeedbackForForm($form) {
                var $feedback = $form.find('.market-seeding-doctrine-feedback');

                if ($feedback.length) {
                    return $feedback;
                }

                return $form.closest('.market-seeding-tracked-doctrine-list, .tab-pane').find('.market-seeding-doctrine-feedback').first();
            }

            function trackedDoctrineSubmitButton($form) {
                var formId = $form.attr('id');

                if (formId) {
                    return $('[form="' + formId + '"][type="submit"]').first();
                }

                return $form.find('button[type="submit"], .market-seeding-preview-doctrine').first();
            }

            function updateDoctrineUi(marketId, response) {
                $('.market-seeding-doctrine-summary-shell[data-market-id="' + marketId + '"]').html(response.summary_html || '');
                $('.market-seeding-tracked-doctrine-list[data-market-id="' + marketId + '"]').html(response.list_html || '');
            }

            function showImportPreviewModal($sourceModal) {
                previewApplying = false;
                previewSourceModal = $sourceModal.length ? $sourceModal : null;

                if ($sourceModal.length && $sourceModal.is(':visible')) {
                    $sourceModal.one('hidden.bs.modal', function () {
                        $('#market-seeding-import-preview-modal').modal('show');
                    });
                    $sourceModal.modal('hide');
                    return;
                }

                $('#market-seeding-import-preview-modal').modal('show');
            }

            function fetchDoctrinePreview($form, $button, $feedback, $sourceModal, showModal, renderFits) {
                var originalButtonHtml = $button.html();
                renderFits = renderFits !== false;

                if ($button.length) {
                    $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Previewing');
                }
                $feedback.hide().removeClass('text-success text-danger').text('');

                $.ajax({
                    url: $form.data('preview-url'),
                    method: 'POST',
                    data: serializePreviewForm($form),
                    headers: {
                        Accept: 'application/json'
                    }
                }).done(function (response) {
                    previewImportForm = $form;
                    renderImportPreview(response, renderFits);
                    $('.market-seeding-doctrine-preview-refresh-status').text(renderFits ? '' : 'Preview updated.');

                    if (showModal) {
                        showImportPreviewModal($sourceModal);
                    }
                }).fail(function (xhr) {
                    $feedback.addClass('text-danger').text(errorMessage(xhr)).show();
                    $('.market-seeding-doctrine-preview-refresh-status').text('Preview could not be updated.');
                }).always(function () {
                    if ($button.length) {
                        $button.prop('disabled', false).html(originalButtonHtml || 'Preview Doctrine');
                    }
                });
            }

            function showSettingsNotice(message, type) {
                var alertClass = type === 'success' ? 'alert-success' : 'alert-info';
                var $notice = $(
                    '<div class="alert ' + alertClass + ' alert-dismissible fade show market-seeding-runtime-notice" role="alert">' +
                        escapeHtml(message) +
                        '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
                            '<span aria-hidden="true">&times;</span>' +
                        '</button>' +
                    '</div>'
                );

                $('.market-seeding-runtime-notice').remove();
                $('.market-seeding-settings-shell').prepend($notice);
            }

            function resetImportForm($form) {
                $form.find('.market-seeding-stock-list').val('');
                $form.find('.market-seeding-profile-selector').val('');
                $form.find('.saved-fitting-selector').val(null).trigger('change');
                $form.find('input[name="multiplier"]').val('1');
                $form.find('input[name="warning_percentage"]').val('33');
                $form.find('select[name="mode"]').val('add');
                $form.find('input[name="keep_higher_quantity"]').prop('checked', true);
            }

            function serializeInlineItemForm($form) {
                var formId = $form.attr('id');
                var linkedFields = formId ? $('[form="' + formId + '"]').serialize() : '';

                return [$form.serialize(), linkedFields].filter(Boolean).join('&');
            }

            function serializePreviewForm($form) {
                var formId = $form.attr('id');
                var fields = $form.serializeArray();

                if (formId) {
                    fields = fields.concat($('[form="' + formId + '"]').serializeArray());
                }

                return $.param($.grep(fields, function (field) {
                    return field.name !== '_method';
                }));
            }

            function renderImportPreview(response, renderDoctrineFits) {
                var summary = response.summary || {};
                var rows = response.rows || [];
                var validation = response.validation || {};
                var summaryText = [
                    (summary.total || 0) + ' item(s)',
                    (summary.new || 0) + ' new',
                    (summary.increase || 0) + ' increased',
                    (summary.replace || 0) + ' replaced',
                    (summary.reduce || 0) + ' reduced',
                    (summary.remove || 0) + ' removed',
                    (summary.unchanged || 0) + ' unchanged'
                ];
                renderDoctrineFits = renderDoctrineFits !== false;

                $('.market-seeding-preview-summary').text(summaryText.join(' · '));
                if (renderDoctrineFits) {
                    renderDoctrinePreviewSettings(response.doctrine || null);
                }
                renderImportValidation(validation);

                if (!rows.length) {
                    $('.market-seeding-preview-rows').html('<tr><td colspan="6" class="text-muted">No valid item lines were found.</td></tr>');
                    return;
                }

                $('.market-seeding-preview-rows').html($.map(rows, function (row) {
                    return '' +
                        '<tr>' +
                            '<td>' + escapeHtml(row.type_name) + '</td>' +
                            '<td>' + previewActionBadge(row.action) + '</td>' +
                            '<td class="text-right">' + formatWhole(row.current_quantity) + '</td>' +
                            '<td class="text-right">' + formatWhole(row.import_quantity) + '</td>' +
                            '<td class="text-right">' + formatWhole(row.new_quantity) + '</td>' +
                            '<td class="text-right">' + formatWhole(row.warning_quantity) + '</td>' +
                        '</tr>';
                }).join(''));
            }

            function renderDoctrinePreviewSettings(doctrine) {
                var $panel = $('.market-seeding-doctrine-preview-settings');
                var fits = doctrine && doctrine.fits ? doctrine.fits : [];

                if (!fits.length) {
                    $panel.hide();
                    $('.market-seeding-doctrine-fit-rows').empty();
                    return;
                }

                if (previewImportForm && previewImportForm.hasClass('market-seeding-tracked-doctrine-form')) {
                    previewImportForm.find('select[name="fit_aggregation_mode"]').val(doctrine.fit_aggregation_mode || 'max');
                    previewImportForm.find('.market-seeding-doctrine-fit-settings').val(JSON.stringify($.map(fits, doctrineFitSettingPayload)));
                }

                $('.market-seeding-doctrine-fit-rows').html($.map(fits, function (fit) {
                    var contentsId = 'market-seeding-fit-contents-' + fit.fitting_id;
                    var fitPanel = doctrineFitPanel(fit);

                    return '' +
                        '<tr data-fitting-id="' + escapeAttr(fit.fitting_id) + '">' +
                            '<td>' +
                                '<strong>' + escapeHtml(fit.ship_type_name || 'Unknown Ship') + '</strong>' +
                                '<div class="small text-muted">' + escapeHtml(fit.fitting_name || 'Unnamed Fit') + '</div>' +
                            '</td>' +
                            '<td class="text-right" style="width: 150px;">' +
                                '<input type="number" class="form-control form-control-sm text-right market-seeding-fit-ship-multiplier" value="' + escapeAttr(fit.ship_multiplier) + '" min="0" max="10000">' +
                            '</td>' +
                            '<td class="text-right" style="width: 165px;">' +
                                '<input type="number" class="form-control form-control-sm text-right market-seeding-fit-fitting-multiplier" value="' + escapeAttr(fit.fitting_multiplier) + '" min="0" max="10000">' +
                            '</td>' +
                            '<td>' +
                                '<button type="button" class="btn btn-default btn-xs" data-toggle="collapse" data-target="#' + contentsId + '">View Fit</button>' +
                                '<div class="collapse market-seeding-fit-contents mt-2" id="' + contentsId + '">' +
                                    fitPanel +
                                '</div>' +
                            '</td>' +
                        '</tr>';
                }).join(''));
                $panel.show();
            }

            function doctrineFitPanel(fit) {
                var groups = groupFitItems(fit.items || []);
                var order = ['High Slots', 'Medium Slots', 'Low Slots', 'Rigs', 'Drone Bay', 'Cargo', 'Service Slots', 'Other'];
                var slotGroups = '';

                $.each(order, function (index, groupName) {
                    var items = groups[groupName] || [];

                    if (!items.length) {
                        return;
                    }

                    slotGroups += '<div class="market-seeding-fit-slot-group">' +
                        '<div class="market-seeding-fit-slot-group-title">' + escapeHtml(groupName) + '</div>' +
                        $.map(items, function (item) {
                            return '<div class="market-seeding-fit-slot-row">' +
                                '<span>' + escapeHtml(item.type_name) + '</span>' +
                                '<span class="text-muted">x' + formatWhole(item.quantity) + '</span>' +
                            '</div>';
                        }).join('') +
                    '</div>';
                });

                if (!slotGroups) {
                    slotGroups = '<div class="text-muted">No fitting items found.</div>';
                }

                return '<div class="market-seeding-fit-panel">' +
                    '<div class="market-seeding-fit-ship">' +
                        '<strong>' + escapeHtml(fit.ship_type_name || 'Unknown Ship') + '</strong>' +
                        '<div class="small text-muted">' + escapeHtml(fit.fitting_name || 'Unnamed Fit') + '</div>' +
                    '</div>' +
                    '<div class="market-seeding-fit-slots">' + slotGroups + '</div>' +
                '</div>';
            }

            function groupFitItems(items) {
                var groups = {};

                $.each(items, function (index, item) {
                    var group = item.slot_group || 'Other';
                    groups[group] = groups[group] || [];
                    groups[group].push(item);
                });

                return groups;
            }

            function writeDoctrineFitSettingsToForm($form) {
                var settings = [];

                $('.market-seeding-doctrine-fit-rows tr[data-fitting-id]').each(function () {
                    var $row = $(this);

                    settings.push({
                        fitting_id: Number($row.data('fitting-id')),
                        ship_multiplier: Number($row.find('.market-seeding-fit-ship-multiplier').val() || 0),
                        fitting_multiplier: Number($row.find('.market-seeding-fit-fitting-multiplier').val() || 0)
                    });
                });

                $form.find('.market-seeding-doctrine-fit-settings').val(JSON.stringify(settings));
            }

            function doctrineFitSettingPayload(fit) {
                return {
                    fitting_id: Number(fit.fitting_id || 0),
                    ship_multiplier: Number(fit.ship_multiplier || 0),
                    fitting_multiplier: Number(fit.fitting_multiplier || 0)
                };
            }

            function renderImportValidation(validation) {
                var skipped = validation.skipped || [];
                var processed = Number(validation.processed_lines || 0);
                var ignored = Number(validation.ignored_lines || 0);
                var $panel = $('.market-seeding-preview-validation');

                if (!skipped.length && !ignored) {
                    $panel.hide().empty();
                    return;
                }

                var html = '<strong>Import validation:</strong> ' +
                    formatWhole(processed) + ' parsed line(s), ' +
                    formatWhole(ignored) + ' ignored EFT/header/blank line(s), ' +
                    formatWhole(skipped.length) + ' skipped line(s).';

                if (skipped.length) {
                    html += '<ul class="market-seeding-validation-list mt-2">';
                    $.each(skipped, function (index, skippedLine) {
                        var prefix = skippedLine.line_number ? 'Line ' + skippedLine.line_number + ': ' : '';
                        html += '<li>' +
                            escapeHtml(prefix) +
                            '<span class="market-seeding-validation-line">' + escapeHtml(skippedLine.line || '') + '</span>' +
                            ' - ' + escapeHtml(skippedLine.reason || 'Could not import this line.') +
                        '</li>';
                    });
                    html += '</ul>';
                }

                $panel.html(html).show();
            }

            function previewActionBadge(action) {
                var labels = {
                    new: ['New', 'badge-success'],
                    increase: ['Increase', 'badge-info'],
                    replace: ['Replace', 'badge-primary'],
                    reduce: ['Reduce', 'badge-warning'],
                    remove: ['Remove', 'badge-danger'],
                    unchanged: ['Unchanged', 'badge-secondary']
                };
                var label = labels[action] || [action || 'Unknown', 'badge-secondary'];

                return '<span class="badge ' + label[1] + '">' + escapeHtml(label[0]) + '</span>';
            }

            function updateTrackedCount($card, count, increment) {
                var $count = $card.find('.market-seeding-tracked-count');

                if (typeof count === 'number') {
                    $count.data('count', count);
                    $count.text(count);
                    return;
                }

                $count.data('count', Number($count.data('count')) + (increment || 0));
                $count.text($count.data('count'));
            }

            function itemRowHtml(item, tableSelector) {
                var updateFormId = 'item-update-' + item.id;

                return '' +
                    '<tr data-item-id="' + item.id + '">' +
                        '<td>' + escapeHtml(item.type_name) + '</td>' +
                        '<td>' + escapeHtml(item.type_category || 'Unknown') + '</td>' +
                        '<td class="market-seeding-source-column">' + (item.source_icons_html || '') + '</td>' +
                        '<td class="text-right" style="width: 140px;" data-order="' + item.desired_quantity + '">' +
	                            '<form id="' + updateFormId + '" action="' + escapeAttr(item.update_url) + '" method="POST" class="market-seeding-update-item-form" data-table="' + escapeAttr(tableSelector) + '" data-original-target-quantity="' + item.desired_quantity + '" data-original-warning-quantity="' + item.warning_quantity + '">' +
                                '<input type="hidden" name="_token" value="' + escapeAttr(csrfToken) + '">' +
                                '<input type="hidden" name="_method" value="PUT">' +
                                '<input type="number" class="form-control form-control-sm text-right" name="desired_quantity" value="' + item.desired_quantity + '" min="1">' +
                            '</form>' +
                        '</td>' +
                        '<td class="text-right" style="width: 140px;" data-order="' + item.warning_quantity + '">' +
                            '<input form="' + updateFormId + '" type="number" class="form-control form-control-sm text-right" name="warning_quantity" value="' + item.warning_quantity + '" min="0">' +
                        '</td>' +
                        '<td class="text-right" style="width: 200px;">' +
                            '<button type="button" class="btn btn-default btn-xs market-seeding-show-target-history" data-history-url="' + escapeAttr(item.history_url) + '" data-item-name="' + escapeAttr(item.type_name) + '" title="Target history"><i class="fas fa-history"></i></button> ' +
                            '<button type="submit" class="btn btn-primary btn-xs market-seeding-save-item" form="' + updateFormId + '">Save</button> ' +
                            '<form action="' + escapeAttr(item.destroy_url) + '" method="POST" class="market-seeding-delete-item-form" data-table="' + escapeAttr(tableSelector) + '" style="display: inline-block;">' +
                                '<input type="hidden" name="_token" value="' + escapeAttr(csrfToken) + '">' +
                                '<input type="hidden" name="_method" value="DELETE">' +
                                '<button type="submit" class="btn btn-danger btn-xs">Delete</button>' +
                            '</form>' +
                        '</td>' +
                    '</tr>';
            }

            function errorMessage(xhr) {
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    var messages = [];
                    $.each(xhr.responseJSON.errors, function (field, fieldMessages) {
                        messages = messages.concat(fieldMessages);
                    });

                    if (messages.length) {
                        return messages.join(' ');
                    }
                }

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    return xhr.responseJSON.message;
                }

                return 'Item could not be saved.';
            }

            function escapeHtml(value) {
                return $('<div>').text(value || '').html();
            }

            function escapeAttr(value) {
                return escapeHtml(value).replace(/"/g, '&quot;');
            }

            function escapeRegex(value) {
                return String(value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            }

            function formatWhole(value) {
                return Number(value || 0).toLocaleString('en-US', {
                    maximumFractionDigits: 0
                });
            }
        });
    </script>
@endpush
