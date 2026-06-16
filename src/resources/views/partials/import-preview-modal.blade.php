<div class="modal fade market-seeding-profile-modal {{ $marketSeedingThemeClass }}" id="market-seeding-import-preview-modal" tabindex="-1" role="dialog" aria-labelledby="market-seeding-import-preview-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="market-seeding-import-preview-modal-label">Import Preview</h5>
                    <small class="text-muted">Review target changes before applying the import.</small>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="market-seeding-doctrine-preview-settings mb-3" style="display: none;">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                        <div>
                            <strong>Doctrine fit multipliers</strong>
                            <div class="small text-muted">Ship multiplier is only the hull. Fitting multiplier covers modules, rigs, ammo, drones, cargo, and other fit contents.</div>
                        </div>
                        <span class="small text-muted market-seeding-doctrine-preview-refresh-status"></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Ship / Fit</th>
                                    <th class="text-right">Ship Multiplier</th>
                                    <th class="text-right">Fitting Multiplier</th>
                                    <th>Fit Contents</th>
                                </tr>
                            </thead>
                            <tbody class="market-seeding-doctrine-fit-rows"></tbody>
                        </table>
                    </div>
                </div>
                <div class="alert alert-light border market-seeding-preview-summary mb-3">
                    Preview has not been loaded yet.
                </div>
                <div class="alert alert-warning market-seeding-preview-validation mb-3" style="display: none;"></div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Action</th>
                                <th class="text-right">Current</th>
                                <th class="text-right">Import</th>
                                <th class="text-right">After Import</th>
                                <th class="text-right">Low Warning</th>
                            </tr>
                        </thead>
                        <tbody class="market-seeding-preview-rows">
                            <tr>
                                <td colspan="6" class="text-muted">No preview loaded.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success market-seeding-run-previewed-import">Apply These Changes</button>
            </div>
        </div>
    </div>
</div>
