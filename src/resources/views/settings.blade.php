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
            justify-content: space-between;
        }
        .market-seeding-card .card-tools {
            display: flex;
            gap: .35rem;
        }
        .market-seeding-location-summary {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: .25rem;
            min-height: 38px;
            padding: .45rem .65rem;
        }
        .market-seeding-location-summary strong {
            display: block;
            line-height: 1.1;
        }
        .market-seeding-location-summary small {
            color: #6c757d;
        }
        .market-seeding-subsection {
            border-top: 1px solid #e9ecef;
            margin-top: 1rem;
            padding-top: 1rem;
        }
        .market-seeding-import-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        }
        .market-seeding-dark-skin .market-seeding-location-summary {
            background: #1f2d3d;
            border-color: #3c4b54;
            color: #e9ecef;
        }
        .market-seeding-dark-skin .market-seeding-location-summary small,
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
    </style>

    <div class="market-seeding-settings-shell {{ $marketSeedingThemeClass }}">
    <div class="card mb-4 market-seeding-card">
        <div class="card-header">
            <h3 class="card-title mb-0">Add Market</h3>
        </div>
        <div class="card-body">
            <form action="{{ route('market-seeding.markets.store') }}" method="POST">
                {{ csrf_field() }}
                <div class="form-row align-items-end">
                    <div class="form-group col-lg-3 col-md-6">
                        <label for="name">Display Name</label>
                        <input type="text" class="form-control" name="name" id="name" placeholder="Home staging" required>
                    </div>
                    <div class="form-group col-lg-4 col-md-6">
                        <label for="location_selector">Station or Structure</label>
                        <select class="form-control market-location-selector" id="location_selector" style="width: 100%;"></select>
                        <input type="hidden" name="location_id" id="location_id" required>
                        <input type="hidden" name="location_name" id="location_name" required>
                        <input type="hidden" name="region_id" id="region_id" value="10000002">
                        <input type="hidden" name="solar_system_id" id="solar_system_id">
                        <input type="hidden" name="is_structure" id="is_structure" value="0">
                    </div>
                    <div class="form-group col-lg-3 col-md-6">
                        <label>Selected Location</label>
                        <div class="market-seeding-location-summary" id="selected_location_summary">
                            <strong>No location selected</strong>
                            <small>Search above, or use manual entry.</small>
                        </div>
                    </div>
                    <div class="form-group col-lg-2 col-md-6">
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

    @foreach($markets as $market)
        @php
            $marketCollapseId = 'market-settings-body-' . $market->id;
            $manualCollapseId = 'manual-location-fields-' . $market->id;
            $selectedSummaryId = 'selected-location-summary-' . $market->id;
        @endphp

        <div class="card mb-4 market-seeding-card">
            <div class="card-header">
                <div>
                    <h3 class="card-title mb-0">{{ $market->name }}</h3>
                    <small class="text-muted">{{ $market->location_name }} &middot; {{ $market->items->count() }} tracked items</small>
                </div>
                <div class="card-tools">
                    <button type="button" class="btn btn-default btn-sm" data-toggle="collapse" data-target="#{{ $marketCollapseId }}" aria-expanded="true" aria-controls="{{ $marketCollapseId }}">
                        <i class="fas fa-sliders-h"></i> Configure
                    </button>
                    <form action="{{ route('market-seeding.markets.refresh', $market->id) }}" method="POST">
                        {{ csrf_field() }}
                        <button type="submit" class="btn btn-info btn-sm">
                            <i class="fas fa-sync"></i> Refresh ESI
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
            <div class="collapse show" id="{{ $marketCollapseId }}">
                <div class="card-body">
                    <form action="{{ route('market-seeding.markets.update', $market->id) }}" method="POST" class="mb-3">
                        {{ csrf_field() }}
                        {{ method_field('PUT') }}
                        <div class="form-row align-items-end">
                            <div class="form-group col-lg-3 col-md-6">
                                <label>Name</label>
                                <input type="text" class="form-control" name="name" value="{{ $market->name }}" required>
                            </div>
                            <div class="form-group col-lg-3 col-md-6">
                                <label>Selected Location</label>
                                <div class="market-seeding-location-summary" id="{{ $selectedSummaryId }}">
                                    <strong>{{ $market->location_name }}</strong>
                                    <small>{{ $market->is_structure ? 'Structure' : 'Station' }}</small>
                                </div>
                            </div>
                            <div class="form-group col-lg-3 col-md-6">
                                <label>Change Location</label>
                                <select class="form-control market-location-selector" style="width: 100%;" data-prefix="market-{{ $market->id }}"></select>
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

                    <div class="market-seeding-import-grid market-seeding-subsection">
                        <div>
                            <h5>Add One Item</h5>
                            <form action="{{ route('market-seeding.items.store', $market->id) }}" method="POST">
                                {{ csrf_field() }}
                                <div class="form-group">
                                    <label>Item</label>
                                    <select name="type_id" class="form-control item-selector" style="width: 100%;" required></select>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-5">
                                        <label>Target Quantity</label>
                                        <input type="number" class="form-control" name="desired_quantity" min="1" required>
                                    </div>
                                    <div class="form-group col-md-5">
                                        <label>Low Warning</label>
                                        <input type="number" class="form-control" name="warning_quantity" min="0">
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label>&nbsp;</label>
                                        <button type="submit" class="btn btn-primary btn-block">Add</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div>
                            <h5>Bulk Import</h5>
                            <form action="{{ route('market-seeding.items.import', $market->id) }}" method="POST">
                                {{ csrf_field() }}
                                <div class="form-group">
                                    <textarea name="stock_list" class="form-control" rows="6" placeholder="[Caracal, Doctrine]
Heavy Missile Launcher II
Scourge Fury Heavy Missile x5000
Caracal 10" required></textarea>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-3">
                                        <label>Multiplier</label>
                                        <input type="number" class="form-control" name="multiplier" value="1" min="1">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>Import Mode</label>
                                        <select name="mode" class="form-control">
                                            <option value="add">Add to targets</option>
                                            <option value="replace">Replace list</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-5">
                                        <label>&nbsp;</label>
                                        <button type="submit" class="btn btn-success btn-block">Import Items</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        @if($savedFittingsAvailable)
                            <div>
                                <h5>Import Saved Fit or Doctrine</h5>
                                <form action="{{ route('market-seeding.items.import-saved-fitting', $market->id) }}" method="POST">
                                    {{ csrf_field() }}
                                    <div class="form-group">
                                        <label>Saved Source</label>
                                        <select name="saved_fitting" class="form-control saved-fitting-selector" style="width: 100%;" required></select>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-3">
                                            <label>Multiplier</label>
                                            <input type="number" class="form-control" name="multiplier" value="1" min="1">
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>Import Mode</label>
                                            <select name="mode" class="form-control">
                                                <option value="add">Add to targets</option>
                                                <option value="replace">Replace list</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-5">
                                            <label>&nbsp;</label>
                                            <button type="submit" class="btn btn-success btn-block">Import Saved Source</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        @endif
                    </div>

                    <div class="table-responsive market-seeding-subsection">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-right">Target</th>
                                    <th class="text-right">Low Warning</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($market->items->sortBy('type_name') as $item)
                                    <tr>
                                        <td>{{ $item->type_name }}</td>
                                        <td class="text-right" style="width: 140px;">
                                            <form id="item-update-{{ $item->id }}" action="{{ route('market-seeding.items.update', $item->id) }}" method="POST">
                                                {{ csrf_field() }}
                                                {{ method_field('PUT') }}
                                                <input type="number" class="form-control form-control-sm text-right" name="desired_quantity" value="{{ $item->desired_quantity }}" min="1">
                                        </td>
                                        <td class="text-right" style="width: 140px;">
                                                <input type="number" class="form-control form-control-sm text-right" name="warning_quantity" value="{{ $item->warning_quantity }}" min="0">
                                            </form>
                                        </td>
                                        <td class="text-right" style="width: 160px;">
                                            <button type="submit" class="btn btn-primary btn-xs" form="item-update-{{ $item->id }}">Save</button>
                                            <form action="{{ route('market-seeding.items.destroy', $item->id) }}" method="POST" style="display: inline-block;" onsubmit="return confirm('Remove this item?');">
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
    @endforeach
    </div>
@endsection

@push('javascript')
    <script>
        $(function () {
            $('.item-selector').select2({
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

            $('.saved-fitting-selector').select2({
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

            $('.market-location-selector').select2({
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
            }).on('select2:select', function (event) {
                var data = event.params.data;
                var prefix = $(this).data('prefix');
                var selectors = prefix ? {
                    locationId: '#' + prefix + '-location_id',
                    locationName: '#' + prefix + '-location_name',
                    regionId: '#' + prefix + '-region_id',
                    solarSystemId: '#' + prefix + '-solar_system_id',
                    isStructure: '#' + prefix + '-is_structure',
                    summary: '#selected-location-summary-' + prefix.replace('market-', '')
                } : {
                    locationId: '#location_id',
                    locationName: '#location_name',
                    regionId: '#region_id',
                    solarSystemId: '#solar_system_id',
                    isStructure: '#is_structure',
                    summary: '#selected_location_summary'
                };

                $(selectors.locationId).val(data.id);
                $(selectors.locationName).val(data.text);
                $(selectors.regionId).val(data.region_id || 10000002);
                $(selectors.solarSystemId).val(data.solar_system_id || '');
                $(selectors.isStructure).val(data.is_structure ? 1 : 0);
                updateLocationSummary(selectors.summary, data.text, data.is_structure ? 'Structure' : 'Station');
            });

            $('.manual-location-id, .manual-location-name, .manual-region-id, .manual-is-structure').on('input change', function () {
                var target = $(this).data('target');
                $(target).val($(this).val());

                if ($(this).hasClass('manual-location-name')) {
                    var form = $(this).closest('form');
                    var summary = form.find('.market-seeding-location-summary').attr('id');
                    var type = form.find('.manual-is-structure').val() === '1' ? 'Structure' : 'Station';
                    updateLocationSummary('#' + summary, $(this).val() || 'Manual location', type);
                }
            });

            function updateLocationSummary(selector, name, type) {
                $(selector).html('<strong>' + escapeHtml(name) + '</strong><small>' + escapeHtml(type) + '</small>');
            }

            function escapeHtml(value) {
                return $('<div>').text(value).html();
            }
        });
    </script>
@endpush
