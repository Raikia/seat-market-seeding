@if($market->trackedSavedFittings->isNotEmpty())
    <div class="table-responsive mt-2">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>Tracked Saved Fit</th>
                    <th class="text-right">Ship Multiplier</th>
                    <th class="text-right">Fit Multiplier</th>
                    <th class="text-right">Low Warning %</th>
                    <th>Manual Handling</th>
                    <th>Last Sync</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($market->trackedSavedFittings->sortBy('fitting_name') as $trackedSavedFitting)
                    @php
                        $shipTypeId = (int) $trackedSavedFitting->ship_type_id;
                        $shipIconUrl = $shipTypeId > 0
                            ? 'https://images.evetech.net/types/' . $shipTypeId . '/render?size=64'
                            : null;
                    @endphp
                    <tr>
                        <td>
                            <div class="market-seeding-saved-fitting-table-title">
                                @if($shipIconUrl)
                                    <img src="{{ $shipIconUrl }}" alt="{{ $trackedSavedFitting->ship_type_name ?: 'Ship' }} image" class="market-seeding-saved-fitting-ship-icon">
                                @endif
                                <div>
                                    {{ $trackedSavedFitting->fitting_name }}
                                    <div class="small text-muted">
                                        {{ $trackedSavedFitting->ship_type_name ?: 'Unknown Ship' }}
                                        &middot;
                                        Character #{{ $trackedSavedFitting->character_id }}
                                    </div>
                                    @if($trackedSavedFitting->last_sync_status)
                                        <div class="small text-muted">{{ ucfirst($trackedSavedFitting->last_sync_status) }}: {{ $trackedSavedFitting->last_sync_message }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="text-right" style="width: 140px;">
                            <form id="tracked-saved-fitting-{{ $trackedSavedFitting->id }}" action="{{ route('market-seeding.tracked-saved-fittings.update', $trackedSavedFitting->id) }}" method="POST" class="market-seeding-tracked-saved-fitting-form" data-market-id="{{ $market->id }}" data-preview-url="{{ route('market-seeding.tracked-saved-fittings.preview', $market->id) }}">
                                {{ csrf_field() }}
                                {{ method_field('PUT') }}
                                <input type="hidden" name="saved_fitting" value="character-fit:{{ $trackedSavedFitting->character_id }}:{{ $trackedSavedFitting->fitting_id }}">
                                <input type="number" name="ship_multiplier" class="form-control form-control-sm text-right" value="{{ $trackedSavedFitting->ship_multiplier }}" min="0" max="10000">
                            </form>
                        </td>
                        <td class="text-right" style="width: 140px;">
                            <input form="tracked-saved-fitting-{{ $trackedSavedFitting->id }}" type="number" name="fitting_multiplier" class="form-control form-control-sm text-right" value="{{ $trackedSavedFitting->fitting_multiplier }}" min="0" max="10000">
                        </td>
                        <td class="text-right" style="width: 130px;">
                            <input form="tracked-saved-fitting-{{ $trackedSavedFitting->id }}" type="number" name="warning_percentage" class="form-control form-control-sm text-right" value="{{ $trackedSavedFitting->warning_percentage }}" min="0" max="100">
                        </td>
                        <td style="width: 260px;">
                            <select form="tracked-saved-fitting-{{ $trackedSavedFitting->id }}" name="merge_mode" class="form-control form-control-sm">
                                <option value="max" @if($trackedSavedFitting->merge_mode === 'max') selected @endif>Use higher of manual or fitting</option>
                                <option value="add" @if($trackedSavedFitting->merge_mode === 'add') selected @endif>Add fitting to manual target</option>
                            </select>
                        </td>
                        <td>
                            {{ optional($trackedSavedFitting->last_synced_at)->diffForHumans() ?: 'Not synced yet' }}
                        </td>
                        <td class="text-right" style="width: 160px;">
                            <button type="button" class="btn btn-default btn-xs market-seeding-edit-tracked-saved-fitting" form="tracked-saved-fitting-{{ $trackedSavedFitting->id }}">View Fit</button>
                            <button type="submit" class="btn btn-primary btn-xs" form="tracked-saved-fitting-{{ $trackedSavedFitting->id }}">Save</button>
                            <form action="{{ route('market-seeding.tracked-saved-fittings.destroy', $trackedSavedFitting->id) }}" method="POST" class="market-seeding-delete-tracked-saved-fitting-form" data-market-id="{{ $market->id }}" style="display: inline-block;">
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
    <p class="text-muted mb-0">No character saved fits are being auto-tracked for this market yet.</p>
@endif
