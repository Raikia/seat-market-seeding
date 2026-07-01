<style>
    .market-seeding-edit-target-modal .history-sparkline {
        display: block;
        height: 28px;
        width: 92px;
    }
    .market-seeding-edit-target-modal .history-sparkline polyline {
        fill: none;
        stroke: #17a2b8;
        stroke-linecap: round;
        stroke-linejoin: round;
        stroke-width: 3;
    }
    .market-seeding-edit-target-modal .edit-target-trend-panel {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        margin-bottom: 1rem;
        padding: .9rem 1rem;
    }
    .market-seeding-edit-target-modal .edit-target-trend-header {
        align-items: center;
        display: flex;
        justify-content: space-between;
        margin-bottom: .65rem;
    }
    .market-seeding-edit-target-modal .edit-target-trend-title {
        font-size: .8rem;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }
    .market-seeding-edit-target-modal .edit-target-trend-summary {
        color: #6c757d;
        font-size: .82rem;
    }
    .market-seeding-edit-target-modal .edit-target-trend-chart {
        height: 180px;
        position: relative;
    }
    .market-seeding-edit-target-modal .edit-target-delta {
        color: #6c757d;
        display: block;
        font-size: .75rem;
        font-weight: 700;
        margin-top: .1rem;
    }
    .market-seeding-edit-target-modal .edit-target-delta.is-positive {
        color: #dc3545;
    }
    .market-seeding-edit-target-modal .edit-target-delta.is-negative {
        color: #28a745;
    }
    .market-seeding-edit-target-modal .modal-dialog {
        max-width: 1060px;
    }
    .market-seeding-edit-target-modal .modal-content {
        border: 0;
        border-radius: 8px;
        overflow: hidden;
    }
    .market-seeding-edit-target-modal .modal-header,
    .market-seeding-edit-target-modal .modal-footer {
        border-color: rgba(0, 0, 0, .08);
    }
    .market-seeding-edit-target-modal .edit-target-hero {
        align-items: stretch;
        background: linear-gradient(135deg, #f8fbfd 0%, #edf4f8 100%);
        border: 1px solid #d9e5ec;
        border-radius: 8px;
        display: grid;
        gap: .85rem;
        grid-template-columns: minmax(0, 1fr) minmax(180px, auto);
        margin-bottom: 1rem;
        padding: 1rem;
    }
    .market-seeding-edit-target-modal .edit-target-hero-main {
        align-items: center;
        display: flex;
        gap: .85rem;
        min-width: 0;
    }
    .market-seeding-edit-target-modal .edit-target-type-icon,
    .market-seeding-edit-target-modal .edit-target-ship-icon {
        background: #111820;
        border: 1px solid rgba(0, 0, 0, .16);
        border-radius: 10px;
        box-shadow: 0 8px 18px rgba(15, 35, 52, .16);
        flex: 0 0 auto;
        object-fit: cover;
    }
    .market-seeding-edit-target-modal .edit-target-type-icon {
        height: 56px;
        width: 56px;
    }
    .market-seeding-edit-target-modal .edit-target-ship-icon {
        height: 42px;
        width: 42px;
    }
    .market-seeding-edit-target-modal .edit-target-item-name {
        display: block;
        font-size: 1.25rem;
        font-weight: 700;
        line-height: 1.2;
    }
    .market-seeding-edit-target-modal .edit-target-market-name {
        color: #607d8b;
        display: block;
        font-size: .9rem;
        margin-top: .25rem;
    }
    .market-seeding-edit-target-modal .edit-target-restock-callout {
        align-items: flex-end;
        display: flex;
        flex-direction: column;
        justify-content: center;
        text-align: right;
    }
    .market-seeding-edit-target-modal .edit-target-restock-label {
        color: #607d8b;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
    }
    .market-seeding-edit-target-modal .edit-target-restock-value {
        color: #dc3545;
        font-size: 1.55rem;
        font-weight: 800;
        line-height: 1.15;
    }
    .market-seeding-edit-target-modal .edit-target-detail-grid {
        display: grid;
        gap: .75rem;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        margin-bottom: 1rem;
    }
    .market-seeding-edit-target-modal .edit-target-detail {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        min-height: 82px;
        padding: .7rem .8rem;
    }
    .market-seeding-edit-target-modal .edit-target-detail-label {
        color: #6c757d;
        display: block;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .03em;
        text-transform: uppercase;
    }
    .market-seeding-edit-target-modal .edit-target-detail-value {
        display: block;
        font-size: 1.08rem;
        font-weight: 700;
        line-height: 1.25;
        margin-top: .15rem;
    }
    .market-seeding-edit-target-modal .edit-target-detail-note {
        color: #6c757d;
        display: block;
        font-size: .75rem;
        margin-top: .1rem;
    }
    .market-seeding-edit-target-modal .edit-target-source-panel {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        margin-bottom: 1rem;
        padding: .9rem 1rem;
    }
    .market-seeding-edit-target-modal .edit-target-source-header {
        align-items: center;
        display: flex;
        justify-content: space-between;
        margin-bottom: .65rem;
    }
    .market-seeding-edit-target-modal .edit-target-source-title {
        font-size: .8rem;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }
    .market-seeding-edit-target-modal .edit-target-source-badges {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem;
    }
    .market-seeding-edit-target-modal .edit-target-source-list {
        display: grid;
        gap: .5rem;
    }
    .market-seeding-edit-target-modal .edit-target-source-card {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: .65rem .75rem;
    }
    .market-seeding-edit-target-modal .edit-target-source-name {
        font-weight: 700;
    }
    .market-seeding-edit-target-modal .edit-target-source-meta,
    .market-seeding-edit-target-modal .edit-target-source-fit-meta {
        color: #6c757d;
        font-size: .78rem;
    }
    .market-seeding-edit-target-modal .edit-target-source-fit {
        align-items: flex-start;
        border-top: 1px solid #dee2e6;
        display: flex;
        gap: .65rem;
        margin-top: .5rem;
        padding-top: .5rem;
    }
    .market-seeding-edit-target-modal .edit-target-source-fit-body {
        min-width: 0;
    }
    .market-seeding-edit-target-modal .edit-target-source-fit-name {
        font-weight: 700;
    }
    .market-seeding-edit-target-modal .edit-target-source-contribution {
        display: inline-block;
        margin-right: .5rem;
        white-space: nowrap;
    }
    .market-seeding-edit-target-modal .edit-target-workspace {
        display: grid;
        gap: 1rem;
        grid-template-columns: minmax(280px, .9fr) minmax(0, 1.35fr);
    }
    .market-seeding-edit-target-modal.is-read-only .edit-target-workspace {
        grid-template-columns: minmax(520px, 1.35fr) minmax(320px, .9fr);
    }
    .market-seeding-edit-target-modal .edit-target-panel {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        min-width: 0;
        padding: 1rem;
    }
    .market-seeding-edit-target-modal .edit-target-panel-title {
        font-size: .8rem;
        font-weight: 800;
        letter-spacing: .04em;
        margin-bottom: .75rem;
        text-transform: uppercase;
    }
    .market-seeding-edit-target-modal .edit-target-form-grid {
        display: grid;
        gap: .75rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .market-seeding-edit-target-modal #market-seeding-edit-target-recommendation {
        background: linear-gradient(135deg, #f8fbfd 0%, #edf4f8 100%);
        border: 1px solid #d9e5ec;
        border-radius: 8px;
        color: #183247;
        margin-bottom: .85rem;
        padding: .75rem .85rem;
    }
    .market-seeding-edit-target-modal .edit-target-recommendation-top {
        align-items: center;
        display: flex;
        gap: .75rem;
        justify-content: space-between;
    }
    .market-seeding-edit-target-modal .edit-target-recommendation-label {
        color: #607d8b;
        font-size: .78rem;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }
    .market-seeding-edit-target-modal .edit-target-recommendation-value {
        color: #183247;
        display: inline-block;
        font-size: 1rem;
        font-weight: 800;
        line-height: 1.15;
        margin-left: .35rem;
    }
    .market-seeding-edit-target-modal .edit-target-recommendation-math {
        background: rgba(24, 50, 71, .07);
        border: 1px solid rgba(24, 50, 71, .12);
        border-radius: 6px;
        font-family: Menlo, Monaco, Consolas, "Liberation Mono", monospace;
        font-size: .76rem;
        line-height: 1.45;
        margin-top: .55rem;
        padding: .45rem .55rem;
    }
    .market-seeding-edit-target-modal .edit-target-recommendation-result {
        font-size: .78rem;
        font-weight: 700;
        margin-top: .45rem;
    }
    .market-seeding-edit-target-modal.market-seeding-dark-skin #market-seeding-edit-target-recommendation {
        background: linear-gradient(135deg, #22313a 0%, #1b272e 100%);
        border-color: #3c4b54;
        color: #f4e7be;
    }
    .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-recommendation-label {
        color: #b8c7ce;
    }
    .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-recommendation-value {
        color: #f4e7be;
    }
    .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-recommendation-math {
        background: rgba(31, 41, 46, .6);
        border-color: rgba(184, 199, 206, .25);
        color: #f4e7be;
    }
    .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-recommendation-result {
        color: #d7eef8;
    }
    .market-seeding-edit-target-modal.market-seeding-dark-skin .history-sparkline polyline {
        stroke: #7bdff2;
    }
    .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-trend-panel,
    .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-source-panel,
    .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-source-card,
    .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-source-fit,
    .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-detail,
    .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-panel {
        background: #1f292e;
        border-color: #3c4b54;
    }
    .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-source-meta,
    .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-source-fit-meta,
    .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-trend-summary {
        color: #b8c7ce;
    }
    .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-delta {
        color: #b8c7ce;
    }
    .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-delta.is-positive {
        color: #ffb3bc;
    }
    .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-delta.is-negative {
        color: #a9e7bd;
    }
    .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-type-icon,
    .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-ship-icon {
        border-color: rgba(244, 231, 190, .18);
        box-shadow: 0 8px 18px rgba(0, 0, 0, .35);
    }
    .market-seeding-edit-target-modal.market-seeding-dark-skin .modal-content {
        background: #2f2927;
        color: #f4e7be;
    }
    .market-seeding-edit-target-modal.market-seeding-dark-skin .modal-header,
    .market-seeding-edit-target-modal.market-seeding-dark-skin .modal-footer {
        border-color: rgba(244, 231, 190, .24);
    }
    .market-seeding-edit-target-modal.market-seeding-dark-skin .close {
        color: #f4e7be;
        opacity: .75;
        text-shadow: none;
    }
    .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-hero {
        background: linear-gradient(135deg, #3b3330 0%, #292523 100%);
        border-color: rgba(244, 231, 190, .22);
    }
    .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-market-name,
    .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-restock-label,
    .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-detail-label,
    .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-detail-note {
        color: #b8c7ce;
    }
    .market-seeding-edit-target-modal.market-seeding-dark-skin .edit-target-restock-value {
        color: #ff9aa7;
    }
    .market-seeding-edit-target-modal.market-seeding-dark-skin .form-control {
        background: #756f6c;
        border-color: #756f6c;
        color: #f4e7be;
    }
    .market-seeding-edit-target-modal.market-seeding-dark-skin .table {
        color: #f4e7be;
    }
    .market-seeding-edit-target-modal.market-seeding-dark-skin .table thead th,
    .market-seeding-edit-target-modal.market-seeding-dark-skin .table td {
        border-color: rgba(244, 231, 190, .24);
    }
    @media (max-width: 991px) {
        .market-seeding-edit-target-modal .edit-target-detail-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .market-seeding-edit-target-modal .edit-target-workspace {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 575px) {
        .market-seeding-edit-target-modal .edit-target-hero,
        .market-seeding-edit-target-modal .edit-target-detail-grid,
        .market-seeding-edit-target-modal .edit-target-form-grid,
        .market-seeding-edit-target-modal .edit-target-workspace {
            grid-template-columns: 1fr;
        }
        .market-seeding-edit-target-modal .edit-target-restock-callout {
            align-items: flex-start;
            text-align: left;
        }
    }
</style>
