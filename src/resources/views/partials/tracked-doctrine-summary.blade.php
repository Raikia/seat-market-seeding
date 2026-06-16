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
            @endphp
            <div class="market-seeding-doctrine-pill">
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
                            <div class="market-seeding-doctrine-fit-summary-row">
                                <div class="market-seeding-doctrine-fit-summary-name">
                                    <strong>{{ $fitSetting->ship_type_name ?: 'Unknown Ship' }}</strong>
                                    <span class="small text-muted">{{ $fitSetting->fitting_name }}</span>
                                </div>
                                <div class="market-seeding-doctrine-fit-summary-badges">
                                    <span class="badge badge-primary" title="Ship hull multiplier">Ship x{{ number_format($fitSetting->ship_multiplier) }}</span>
                                    <span class="badge badge-info" title="Fitting/module multiplier">Fit x{{ number_format($fitSetting->fitting_multiplier) }}</span>
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
