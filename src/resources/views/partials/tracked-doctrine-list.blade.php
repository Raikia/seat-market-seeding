@if($market->trackedDoctrines->isNotEmpty())
    <div class="table-responsive mt-2">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>Tracked Doctrine</th>
                    <th class="text-right">Multiplier</th>
                    <th class="text-right">Low Warning %</th>
                    <th>Merge Mode</th>
                    <th>Last Sync</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($market->trackedDoctrines->sortBy('doctrine_name') as $trackedDoctrine)
                    <tr>
                        <td>
                            {{ $trackedDoctrine->doctrine_name }}
                            @if($trackedDoctrine->last_sync_status)
                                <div class="small text-muted">{{ ucfirst($trackedDoctrine->last_sync_status) }}: {{ $trackedDoctrine->last_sync_message }}</div>
                            @endif
                        </td>
                        <td class="text-right" style="width: 120px;">
                            <form id="tracked-doctrine-{{ $trackedDoctrine->id }}" action="{{ route('market-seeding.tracked-doctrines.update', $trackedDoctrine->id) }}" method="POST" class="market-seeding-tracked-doctrine-form" data-market-id="{{ $market->id }}">
                                {{ csrf_field() }}
                                {{ method_field('PUT') }}
                                <input type="number" name="multiplier" class="form-control form-control-sm text-right" value="{{ $trackedDoctrine->multiplier }}" min="1" max="10000">
                            </form>
                        </td>
                        <td class="text-right" style="width: 130px;">
                            <input form="tracked-doctrine-{{ $trackedDoctrine->id }}" type="number" name="warning_percentage" class="form-control form-control-sm text-right" value="{{ $trackedDoctrine->warning_percentage }}" min="1" max="100">
                        </td>
                        <td style="width: 260px;">
                            <select form="tracked-doctrine-{{ $trackedDoctrine->id }}" name="merge_mode" class="form-control form-control-sm">
                                <option value="max" @if($trackedDoctrine->merge_mode === 'max') selected @endif>Use higher of manual or doctrine</option>
                                <option value="add" @if($trackedDoctrine->merge_mode === 'add') selected @endif>Add doctrine to manual target</option>
                            </select>
                        </td>
                        <td>
                            {{ optional($trackedDoctrine->last_synced_at)->diffForHumans() ?: 'Not synced yet' }}
                        </td>
                        <td class="text-right" style="width: 160px;">
                            <button type="submit" class="btn btn-primary btn-xs" form="tracked-doctrine-{{ $trackedDoctrine->id }}">Save</button>
                            <form action="{{ route('market-seeding.tracked-doctrines.destroy', $trackedDoctrine->id) }}" method="POST" class="market-seeding-delete-tracked-doctrine-form" data-market-id="{{ $market->id }}" style="display: inline-block;">
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
@else
    <p class="text-muted mb-0">No Seat-Fitting doctrines are being auto-tracked for this market yet.</p>
@endif
