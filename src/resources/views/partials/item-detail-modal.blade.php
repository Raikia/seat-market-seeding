<div class="modal fade market-seeding-edit-target-modal {{ $marketSeedingThemeClass }}" id="market-seeding-edit-target-modal" tabindex="-1" role="dialog" aria-labelledby="market-seeding-edit-target-title" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form method="POST" class="modal-content" id="market-seeding-edit-target-form">
            {{ csrf_field() }}
            {{ method_field('PUT') }}
            <div class="modal-header">
                <h5 class="modal-title" id="market-seeding-edit-target-title">{{ ($canManageMarketSeeding ?? false) ? 'Edit Target Stock' : 'Item Details' }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-success d-none" id="market-seeding-edit-target-success"></div>
                <div class="alert alert-danger d-none" id="market-seeding-edit-target-error"></div>
                <div class="edit-target-hero">
                    <div class="edit-target-hero-main">
                        <img src="" alt="" class="edit-target-type-icon d-none" id="market-seeding-edit-target-icon">
                        <div>
                            <span class="edit-target-item-name" id="market-seeding-edit-target-item"></span>
                            <span class="edit-target-market-name" id="market-seeding-edit-target-market"></span>
                        </div>
                    </div>
                    <div class="edit-target-restock-callout">
                        <span class="edit-target-restock-label">Restock Needed</span>
                        <span class="edit-target-restock-value" id="market-seeding-detail-hero-missing">-</span>
                    </div>
                </div>
                <div class="edit-target-detail-grid" id="market-seeding-edit-target-details">
                    <div class="edit-target-detail">
                        <span class="edit-target-detail-label">Current Stock</span>
                        <span class="edit-target-detail-value" id="market-seeding-detail-current">-</span>
                        <span class="edit-target-detail-note">Listed on this market</span>
                    </div>
                    <div class="edit-target-detail">
                        <span class="edit-target-detail-label">Missing</span>
                        <span class="edit-target-detail-value" id="market-seeding-detail-missing">-</span>
                        <span class="edit-target-delta" id="market-seeding-detail-missing-delta"></span>
                        <span class="edit-target-detail-note">Needed to hit target</span>
                    </div>
                    <div class="edit-target-detail">
                        <span class="edit-target-detail-label">Market Price</span>
                        <span class="edit-target-detail-value" id="market-seeding-detail-local-price">-</span>
                        <span class="edit-target-detail-note" id="market-seeding-detail-price-delta">vs Jita</span>
                    </div>
                    <div class="edit-target-detail">
                        <span class="edit-target-detail-label">Jita Price</span>
                        <span class="edit-target-detail-value" id="market-seeding-detail-jita-price">-</span>
                        <span class="edit-target-detail-note">Per item estimate</span>
                    </div>
                    <div class="edit-target-detail">
                        <span class="edit-target-detail-label">Seeded Value</span>
                        <span class="edit-target-detail-value" id="market-seeding-detail-seeded-value">-</span>
                        <span class="edit-target-detail-note">Current stock value</span>
                    </div>
                    <div class="edit-target-detail">
                        <span class="edit-target-detail-label">Target Value</span>
                        <span class="edit-target-detail-value" id="market-seeding-detail-target-value">-</span>
                        <span class="edit-target-delta" id="market-seeding-detail-target-value-delta"></span>
                        <span class="edit-target-detail-note">Full target at Jita</span>
                    </div>
                    <div class="edit-target-detail">
                        <span class="edit-target-detail-label">Restock Value</span>
                        <span class="edit-target-detail-value" id="market-seeding-detail-restock-value">-</span>
                        <span class="edit-target-delta" id="market-seeding-detail-restock-value-delta"></span>
                        <span class="edit-target-detail-note">Missing amount at Jita</span>
                    </div>
                    <div class="edit-target-detail">
                        <span class="edit-target-detail-label">Restock Volume</span>
                        <span class="edit-target-detail-value" id="market-seeding-detail-restock-volume">-</span>
                        <span class="edit-target-delta" id="market-seeding-detail-restock-volume-delta"></span>
                        <span class="edit-target-detail-note" id="market-seeding-detail-item-volume">Packaged m3</span>
                    </div>
                </div>
                <div class="edit-target-trend-panel">
                    <div class="edit-target-trend-header">
                        <div class="edit-target-trend-title">Estimated Sales Trend</div>
                        <div class="edit-target-trend-summary" id="market-seeding-detail-trend-summary">Loading...</div>
                    </div>
                    <div class="edit-target-trend-chart">
                        <canvas id="market-seeding-detail-trend-chart"></canvas>
                    </div>
                </div>
                <div class="edit-target-source-panel">
                    <div class="edit-target-source-header">
                        <div class="edit-target-source-title">Source</div>
                        <div class="edit-target-source-badges" id="market-seeding-detail-source-badges"></div>
                    </div>
                    <div class="edit-target-source-list" id="market-seeding-detail-source-list">
                        <div class="text-muted">Loading source details...</div>
                    </div>
                </div>
                <div class="edit-target-workspace">
                    <div class="edit-target-panel" id="market-seeding-edit-target-adjust-panel">
                        <div class="edit-target-panel-title">Adjust Target</div>
                        <div class="alert alert-info" id="market-seeding-edit-target-recommendation">
                            <div class="edit-target-recommendation-top">
                                <div>
                                    <span class="edit-target-recommendation-label">Sales Recommendation</span>
                                    <span class="edit-target-recommendation-value" id="market-seeding-edit-target-recommended-value"></span>
                                </div>
                                <button type="button" class="btn btn-info btn-sm ml-3" id="market-seeding-use-recommended-target">Use</button>
                            </div>
                            <div class="edit-target-recommendation-math" id="market-seeding-edit-target-recommendation-math"></div>
                            <div class="edit-target-recommendation-result" id="market-seeding-edit-target-recommendation-result"></div>
                            <div class="small history-recommendation-config mt-2">
                                Recommendations use the configured sales window and buffer, not low/empty events.
                                Doctrine-linked items cannot be saved below the amount required by their tracked doctrine sources.
                            </div>
                        </div>
                        <div class="edit-target-form-grid">
                            <div class="form-group mb-0">
                                <label for="market-seeding-edit-target-quantity">Target Stock</label>
                                <input type="number" min="1" class="form-control" id="market-seeding-edit-target-quantity" name="desired_quantity" required>
                            </div>
                            <div class="form-group mb-0">
                                <label for="market-seeding-edit-warning-quantity">Low Warning</label>
                                <input type="number" min="0" class="form-control" id="market-seeding-edit-warning-quantity" name="warning_quantity">
                                <small class="form-text text-muted">Scales with target using the current warning percentage.</small>
                            </div>
                        </div>
                    </div>
                    <div class="edit-target-panel edit-target-transitions-panel">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="edit-target-panel-title mb-0">Recent Stock Transitions</div>
                            <small class="text-muted">Latest 25 events</small>
                        </div>
                        <div class="modal-history-table">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>When</th>
                                        <th>Status</th>
                                        <th class="text-right">Current Stock</th>
                                        <th class="text-right">Warning</th>
                                        <th class="text-right">Target</th>
                                    </tr>
                                </thead>
                                <tbody id="market-seeding-edit-target-history">
                                    <tr>
                                        <td colspan="5" class="text-muted">Select an item to load transition history.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="edit-target-panel edit-target-target-history-panel">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="edit-target-panel-title mb-0">Target Changes</div>
                            <small class="text-muted">Latest 25 changes</small>
                        </div>
                        <div class="modal-history-table">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>When</th>
                                        <th>Type</th>
                                        <th>Changed By</th>
                                        <th class="text-right">Target</th>
                                        <th class="text-right">Warning</th>
                                    </tr>
                                </thead>
                                <tbody id="market-seeding-edit-target-change-history">
                                    <tr>
                                        <td colspan="5" class="text-muted">Select an item to load target changes.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                @if($canManageMarketSeeding ?? false)
                    <button type="submit" class="btn btn-primary" id="market-seeding-edit-target-save">Save Target</button>
                @endif
            </div>
        </form>
    </div>
</div>
