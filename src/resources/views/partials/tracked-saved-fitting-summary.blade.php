@if($market->trackedSavedFittings->isNotEmpty())
    <div class="market-seeding-saved-fitting-summary">
        @foreach($market->trackedSavedFittings->sortBy('fitting_name') as $trackedSavedFitting)
            @php
                $syncBadge = [
                    'success' => 'badge-success',
                    'missing' => 'badge-warning',
                    'error' => 'badge-danger',
                ][$trackedSavedFitting->last_sync_status] ?? 'badge-secondary';
                $shipTypeId = (int) $trackedSavedFitting->ship_type_id;
                $shipIconUrl = $shipTypeId > 0
                    ? 'https://images.evetech.net/types/' . $shipTypeId . '/render?size=64'
                    : null;
            @endphp
            <div class="market-seeding-saved-fitting-pill"
                data-preview-url="{{ route('market-seeding.tracked-saved-fittings.preview', $market->id) }}"
                data-market-id="{{ $market->id }}"
                data-saved-fitting="character-fit:{{ $trackedSavedFitting->character_id }}:{{ $trackedSavedFitting->fitting_id }}"
                data-ship-multiplier="{{ $trackedSavedFitting->ship_multiplier }}"
                data-fitting-multiplier="{{ $trackedSavedFitting->fitting_multiplier }}"
                data-warning-percentage="{{ $trackedSavedFitting->warning_percentage }}"
                data-merge-mode="{{ $trackedSavedFitting->merge_mode }}">
                <div class="market-seeding-linked-card-label">
                    <i class="fas fa-paper-plane"></i>
                    Saved fit
                </div>
                <div class="market-seeding-summary-card-heading">
                    <div class="market-seeding-saved-fitting-heading">
                        @if($shipIconUrl)
                            <img src="{{ $shipIconUrl }}" alt="{{ $trackedSavedFitting->ship_type_name ?: 'Ship' }} image" class="market-seeding-saved-fitting-ship-icon">
                        @endif
                        <strong>{{ $trackedSavedFitting->fitting_name }}</strong>
                    </div>
                    <button type="button" class="btn btn-default btn-xs market-seeding-review-summary-saved-fit" title="Review fit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                <span class="small text-muted">
                    {{ $trackedSavedFitting->ship_type_name ?: 'Unknown Ship' }}
                    &middot;
                    ship x{{ number_format($trackedSavedFitting->ship_multiplier) }}
                    &middot;
                    fit x{{ number_format($trackedSavedFitting->fitting_multiplier) }}
                    &middot;
                    low warning {{ number_format($trackedSavedFitting->warning_percentage) }}%
                    &middot;
                    {{ $trackedSavedFitting->merge_mode === 'add' ? 'adds to manual target' : 'higher of manual or fitting' }}
                </span>
                <div class="small mt-1">
                    <span class="badge {{ $syncBadge }}">{{ ucfirst($trackedSavedFitting->last_sync_status ?: 'not synced') }}</span>
                    @if($trackedSavedFitting->last_synced_at)
                        <span class="text-muted">{{ $trackedSavedFitting->last_synced_at->diffForHumans() }}</span>
                    @endif
                    @if($trackedSavedFitting->last_sync_message)
                        <span class="text-muted">&middot; {{ $trackedSavedFitting->last_sync_message }}</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
