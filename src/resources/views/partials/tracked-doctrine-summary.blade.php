@if($market->trackedDoctrines->isNotEmpty())
    <div class="market-seeding-doctrine-summary">
        @foreach($market->trackedDoctrines->sortBy('doctrine_name') as $trackedDoctrine)
            @php
                $syncBadge = [
                    'success' => 'badge-success',
                    'skipped' => 'badge-warning',
                    'missing' => 'badge-warning',
                    'error' => 'badge-danger',
                ][$trackedDoctrine->last_sync_status] ?? 'badge-secondary';
                $fitSettings = $trackedDoctrine->fitSettings->sortBy('ship_type_name')->values();
                $visibleFitSettings = $fitSettings->take(6);
                $hiddenFitCount = max(0, $fitSettings->count() - $visibleFitSettings->count());
                $fitSettingsPayload = $fitSettings->map(function ($fitSetting) {
                    return [
                        'fitting_id' => (int) $fitSetting->fitting_id,
                        'ship_multiplier' => (int) $fitSetting->ship_multiplier,
                        'fitting_multiplier' => (int) $fitSetting->fitting_multiplier,
                    ];
                })->values();
            @endphp
            <div class="market-seeding-doctrine-pill"
                data-preview-url="{{ route('market-seeding.tracked-doctrines.preview', $market->id) }}"
                data-market-id="{{ $market->id }}"
                data-doctrine-id="{{ $trackedDoctrine->doctrine_id }}"
                data-multiplier="{{ $trackedDoctrine->multiplier }}"
                data-warning-percentage="{{ $trackedDoctrine->warning_percentage }}"
                data-merge-mode="{{ $trackedDoctrine->merge_mode }}"
                data-fit-aggregation-mode="{{ $trackedDoctrine->fit_aggregation_mode }}"
                data-fit-settings='@json($fitSettingsPayload)'>
                <div class="market-seeding-linked-card-label market-seeding-doctrine-card-label">
                    <i class="fas fa-sitemap"></i>
                    Doctrine
                </div>
                <strong>{{ $trackedDoctrine->doctrine_name }}</strong>
                <span class="small text-muted">
                    x{{ number_format($trackedDoctrine->multiplier) }}
                    &middot;
                    low warning {{ number_format($trackedDoctrine->warning_percentage) }}%
                    &middot;
                    {{ $trackedDoctrine->merge_mode === 'add' ? 'adds to manual target' : 'higher of manual or doctrine' }}
                    &middot;
                    {{ $trackedDoctrine->fit_aggregation_mode === 'max' ? 'max per fit item' : 'sums fits' }}
                </span>
                <div class="small mt-1">
                    <span class="badge {{ $syncBadge }}">{{ ucfirst($trackedDoctrine->last_sync_status ?: 'not synced') }}</span>
                    @if($trackedDoctrine->last_synced_at)
                        <span class="text-muted">{{ $trackedDoctrine->last_synced_at->diffForHumans() }}</span>
                    @endif
                </div>
                @if($fitSettings->isNotEmpty())
                    <div class="market-seeding-doctrine-fit-summary">
                        @foreach($visibleFitSettings as $fitSetting)
                            @php
                                $fitShipTypeId = (int) $fitSetting->ship_type_id;
                                $fitShipIconUrl = $fitShipTypeId > 0
                                    ? 'https://images.evetech.net/types/' . $fitShipTypeId . '/render?size=64'
                                    : null;
                            @endphp
                            <div class="market-seeding-doctrine-fit-summary-row">
                                <div class="market-seeding-doctrine-fit-summary-name">
                                    @if($fitShipIconUrl)
                                        <img src="{{ $fitShipIconUrl }}" alt="{{ $fitSetting->ship_type_name ?: 'Ship' }} image" class="market-seeding-linked-fit-ship-icon">
                                    @endif
                                    <div class="market-seeding-doctrine-fit-summary-copy">
                                        <strong>{{ $fitSetting->ship_type_name ?: 'Unknown Ship' }}</strong>
                                        <span class="small text-muted">{{ $fitSetting->fitting_name }}</span>
                                    </div>
                                </div>
                                <div class="market-seeding-doctrine-fit-summary-badges">
                                    <span class="badge badge-primary" title="Ship hull multiplier">Ship x{{ number_format($fitSetting->ship_multiplier) }}</span>
                                    <span class="badge badge-info" title="Fitting/module multiplier">Fit x{{ number_format($fitSetting->fitting_multiplier) }}</span>
                                    <button type="button"
                                        class="btn btn-default btn-xs market-seeding-review-summary-fit"
                                        data-fitting-id="{{ $fitSetting->fitting_id }}"
                                        title="Review fit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                        @if($hiddenFitCount > 0)
                            <div class="small text-muted text-center">
                                +{{ number_format($hiddenFitCount) }} more fit{{ $hiddenFitCount === 1 ? '' : 's' }}
                            </div>
                        @endif
                    </div>
                @else
                    <div class="small text-muted mt-1">Fit multipliers will appear after the next doctrine sync.</div>
                @endif
            </div>
        @endforeach
    </div>
@endif
