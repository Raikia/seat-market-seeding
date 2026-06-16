@php
    $modalId = 'market-add-modal-' . $market->id;
    $addTabId = 'market-add-one-' . $market->id;
    $bulkTabId = 'market-add-bulk-' . $market->id;
    $savedTabId = 'market-add-saved-' . $market->id;
    $doctrineTabId = 'market-add-doctrine-' . $market->id;
    $tableSelector = '#market-seeding-settings-table-' . $market->id;
    $cardSelector = '#market-seeding-card-' . $market->id;
@endphp

<div class="modal fade market-seeding-add-modal" id="{{ $modalId }}" tabindex="-1" role="dialog" aria-labelledby="{{ $modalId }}-label" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="{{ $modalId }}-label">Add Stock Targets: {{ $market->name }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="{{ $addTabId }}-tab" data-toggle="tab" href="#{{ $addTabId }}" role="tab" aria-controls="{{ $addTabId }}" aria-selected="true">One Item</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="{{ $bulkTabId }}-tab" data-toggle="tab" href="#{{ $bulkTabId }}" role="tab" aria-controls="{{ $bulkTabId }}" aria-selected="false">Bulk Import</a>
                    </li>
                    @if($savedFittingsAvailable)
                        <li class="nav-item">
                            <a class="nav-link" id="{{ $savedTabId }}-tab" data-toggle="tab" href="#{{ $savedTabId }}" role="tab" aria-controls="{{ $savedTabId }}" aria-selected="false">Saved Fit</a>
                        </li>
                    @endif
                    @if($seatFittingAvailable)
                        <li class="nav-item">
                            <a class="nav-link" id="{{ $doctrineTabId }}-tab" data-toggle="tab" href="#{{ $doctrineTabId }}" role="tab" aria-controls="{{ $doctrineTabId }}" aria-selected="false">Tracked Doctrines</a>
                        </li>
                    @endif
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="{{ $addTabId }}" role="tabpanel" aria-labelledby="{{ $addTabId }}-tab">
                        <form action="{{ route('market-seeding.items.store', $market->id) }}" method="POST" class="market-seeding-add-item-form" data-table="{{ $tableSelector }}" data-card="{{ $cardSelector }}">
                            {{ csrf_field() }}
                            <div class="form-group">
                                <label>Item</label>
                                <select name="type_id" class="form-control item-selector" style="width: 100%;" required></select>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-5">
                                    <label>Target Quantity</label>
                                    <input type="number" class="form-control" name="desired_quantity" value="1" min="1" required>
                                </div>
                                <div class="form-group col-md-5">
                                    <label>Low Warning %</label>
                                    <input type="number" class="form-control" name="warning_percentage" value="33" min="0" max="100" required>
                                </div>
                                <div class="form-group col-md-2">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-primary btn-block">Add</button>
                                </div>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="keep_higher_quantity" value="1" id="keep-higher-add-{{ $market->id }}" checked>
                                <label class="form-check-label" for="keep-higher-add-{{ $market->id }}">
                                    Keep higher existing targets instead of adding smaller duplicate quantities
                                </label>
                            </div>
                            <div class="market-seeding-add-feedback small mt-2" style="display: none;"></div>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="{{ $bulkTabId }}" role="tabpanel" aria-labelledby="{{ $bulkTabId }}-tab">
                        <form action="{{ route('market-seeding.items.import', $market->id) }}" method="POST" class="market-seeding-import-form" data-preview-url="{{ route('market-seeding.items.preview', $market->id) }}" data-table="{{ $tableSelector }}" data-card="{{ $cardSelector }}">
                            {{ csrf_field() }}
                            @if($profiles->isNotEmpty())
                                <div class="market-seeding-profile-loader">
                                    <label>Load Market Profile</label>
                                    <div class="input-group">
                                        <select class="form-control market-seeding-profile-selector">
                                            <option value="">Choose a saved profile</option>
                                            @foreach($profiles as $profile)
                                                <option value="{{ $profile->id }}">{{ $profile->name }}</option>
                                            @endforeach
                                        </select>
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-default market-seeding-load-profile">
                                                <i class="fas fa-layer-group"></i> Load
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            <div class="form-group">
                                <textarea name="stock_list" class="form-control market-seeding-stock-list" rows="9" placeholder="[Caracal, Doctrine]
Heavy Missile Launcher II
Scourge Fury Heavy Missile x5000
Caracal 10" required></textarea>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label>Multiplier</label>
                                    <input type="number" class="form-control" name="multiplier" value="1" min="1">
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Low Warning %</label>
                                    <input type="number" class="form-control" name="warning_percentage" value="33" min="0" max="100" required>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Import Mode</label>
                                    <select name="mode" class="form-control">
                                        <option value="add">Add to targets</option>
                                        <option value="replace">Replace manual list</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>&nbsp;</label>
                                    <button type="button" class="btn btn-success btn-block market-seeding-preview-import">Preview Import</button>
                                </div>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="keep_higher_quantity" value="1" id="keep-higher-import-{{ $market->id }}" checked>
                                <label class="form-check-label" for="keep-higher-import-{{ $market->id }}">
                                    Keep higher existing manual targets instead of adding smaller duplicate quantities (add mode only)
                                </label>
                            </div>
                            <div class="market-seeding-import-feedback small mt-2" style="display: none;"></div>
                        </form>
                    </div>

                    @if($savedFittingsAvailable)
                        <div class="tab-pane fade" id="{{ $savedTabId }}" role="tabpanel" aria-labelledby="{{ $savedTabId }}-tab">
                            <form action="{{ route('market-seeding.items.import-saved-fitting', $market->id) }}" method="POST" class="market-seeding-import-form" data-preview-url="{{ route('market-seeding.items.preview-saved-fitting', $market->id) }}" data-table="{{ $tableSelector }}" data-card="{{ $cardSelector }}">
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
                                    <div class="form-group col-md-3">
                                        <label>Low Warning %</label>
                                        <input type="number" class="form-control" name="warning_percentage" value="33" min="0" max="100" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>Import Mode</label>
                                        <select name="mode" class="form-control">
                                            <option value="add">Add to targets</option>
                                            <option value="replace">Replace manual list</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>&nbsp;</label>
                                        <button type="button" class="btn btn-success btn-block market-seeding-preview-import">Preview Import</button>
                                    </div>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" name="keep_higher_quantity" value="1" id="keep-higher-saved-{{ $market->id }}" checked>
                                    <label class="form-check-label" for="keep-higher-saved-{{ $market->id }}">
                                        Keep higher existing manual targets instead of adding smaller duplicate quantities (add mode only)
                                    </label>
                                </div>
                                <div class="market-seeding-import-feedback small mt-2" style="display: none;"></div>
                            </form>
                        </div>
                    @endif

                    @if($seatFittingAvailable)
                        <div class="tab-pane fade" id="{{ $doctrineTabId }}" role="tabpanel" aria-labelledby="{{ $doctrineTabId }}-tab">
                            <form action="{{ route('market-seeding.tracked-doctrines.store', $market->id) }}" method="POST" class="market-seeding-tracked-doctrine-form" data-market-id="{{ $market->id }}" data-preview-url="{{ route('market-seeding.tracked-doctrines.preview', $market->id) }}">
                                {{ csrf_field() }}
                                <input type="hidden" name="doctrine_fit_settings" class="market-seeding-doctrine-fit-settings" value="">
                                <div class="form-row">
                                    <div class="form-group col-lg-4 col-md-6">
                                        <label>Doctrine</label>
                                        <select name="doctrine_id" class="form-control doctrine-selector" style="width: 100%;" required></select>
                                    </div>
                                    <div class="form-group col-lg-2 col-md-3">
                                        <label>Default Fit Multiplier</label>
                                        <input type="number" name="multiplier" class="form-control" value="10" min="1" max="10000" required>
                                        <small class="form-text text-muted">Used as the starting ship and fitting multiplier for every fit in the preview.</small>
                                    </div>
                                    <div class="form-group col-lg-2 col-md-3">
                                        <label>Low Warning %</label>
                                        <input type="number" name="warning_percentage" class="form-control" value="33" min="0" max="100" required>
                                    </div>
                                    <div class="form-group col-lg-2 col-md-6">
                                        <label>Manual Handling</label>
                                        <select name="merge_mode" class="form-control">
                                            <option value="max">Use higher of manual or doctrine</option>
                                            <option value="add">Add doctrine to manual target</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-lg-2 col-md-6">
                                        <label>Fit Item Handling</label>
                                        <select name="fit_aggregation_mode" class="form-control">
                                            <option value="max">Use max per item</option>
                                            <option value="sum">Sum all fits</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-12 text-right">
                                        <label>&nbsp;</label>
                                        <button type="button" class="btn btn-success market-seeding-preview-doctrine">Preview Doctrine</button>
                                    </div>
                                </div>
                                <div class="market-seeding-doctrine-feedback small mb-2" style="display: none;"></div>
                            </form>

                            <div class="market-seeding-tracked-doctrine-list" data-market-id="{{ $market->id }}">
                                @include('seat-market-seeding::partials.tracked-doctrine-list', ['market' => $market])
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
