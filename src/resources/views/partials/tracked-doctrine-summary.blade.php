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
            </div>
        @endforeach
    </div>
@endif
