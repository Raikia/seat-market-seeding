<style>
    .market-seeding-fit-panel {
        background:
            radial-gradient(circle at 12% 8%, rgba(120, 170, 190, .18), transparent 28%),
            radial-gradient(circle at 92% 78%, rgba(180, 90, 45, .18), transparent 30%),
            linear-gradient(180deg, rgba(8, 13, 15, .96), rgba(10, 14, 16, .98));
        border: 1px solid rgba(97, 140, 153, .45);
        border-radius: .2rem;
        box-shadow: inset 0 0 30px rgba(255, 255, 255, .03), 0 10px 30px rgba(0, 0, 0, .25);
        color: #d7dde0;
        font-family: "Source Sans Pro", "Helvetica Neue", Arial, sans-serif;
        margin: 0 auto;
        max-width: 430px;
        overflow: hidden;
        padding: .55rem;
    }
    .market-seeding-fit-window-bar {
        align-items: center;
        color: #c3c9ca;
        display: flex;
        font-size: .98rem;
        gap: .55rem;
        letter-spacing: .01em;
        margin-bottom: .5rem;
    }
    .market-seeding-fit-header {
        align-items: flex-start;
        display: grid;
        gap: .65rem;
        grid-template-columns: 72px 1fr;
        margin-bottom: .5rem;
    }
    .market-seeding-fit-ship {
        min-width: 0;
    }
    .market-seeding-fit-ship-icon {
        background: #050809;
        border: 1px solid rgba(255, 255, 255, .12);
        border-radius: 0;
        height: 72px;
        object-fit: cover;
        width: 72px;
    }
    .market-seeding-fit-name-label {
        color: rgba(216, 221, 222, .55);
        font-size: .78rem;
        letter-spacing: .04em;
        margin-bottom: .2rem;
    }
    .market-seeding-fit-name-box {
        align-items: center;
        background: rgba(0, 0, 0, .5);
        border: 1px solid rgba(255, 255, 255, .08);
        color: #f5f8f8;
        display: flex;
        font-size: .95rem;
        justify-content: space-between;
        line-height: 1.2;
        min-height: 34px;
        padding: .35rem .5rem;
    }
    .market-seeding-fit-name-actions {
        color: #aab4b6;
        display: inline-flex;
        gap: .5rem;
        margin-left: .5rem;
    }
    .market-seeding-fit-info-button {
        background: transparent;
        border: 0;
        color: rgba(216, 221, 222, .62);
        line-height: 1;
        padding: 0;
    }
    .market-seeding-fit-info-button:hover,
    .market-seeding-fit-info-button:focus {
        color: #4fd7ee;
        outline: 0;
    }
    .market-seeding-fit-tabs {
        border-bottom: 1px solid rgba(87, 198, 219, .35);
        color: rgba(216, 221, 222, .55);
        display: flex;
        margin: .2rem 0 .55rem;
        padding-left: .05rem;
    }
    .market-seeding-fit-tab-active {
        color: #f5f8f8;
        padding-bottom: .28rem;
        position: relative;
    }
    .market-seeding-fit-tab-active:after {
        background: #4fd7ee;
        box-shadow: 0 0 9px rgba(79, 215, 238, .8);
        bottom: -1px;
        content: "";
        height: 2px;
        left: 0;
        position: absolute;
        right: 0;
    }
    .market-seeding-fit-slots {
        display: block;
    }
    .market-seeding-fit-slot-group {
        background: transparent;
        border: 0;
        border-radius: 0;
        padding: 0;
    }
    .market-seeding-fit-slot-group-title {
        background: rgba(255, 255, 255, .08);
        color: #f1f4f4;
        font-size: .86rem;
        font-weight: 500;
        letter-spacing: .01em;
        margin: .35rem 0 .15rem;
        padding: .32rem .45rem;
        text-transform: none;
    }
    .market-seeding-fit-slot-row {
        align-items: center;
        display: flex;
        gap: .55rem;
        line-height: 1.25;
        min-height: 30px;
        padding: .17rem .25rem;
    }
    .market-seeding-fit-item-icon {
        border-radius: 0;
        height: 24px;
        width: 24px;
    }
    .market-seeding-fit-slot-name {
        flex: 1;
        font-size: .88rem;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .market-seeding-fit-row-status {
        align-items: center;
        color: rgba(216, 221, 222, .55);
        display: inline-flex;
        flex: 0 0 auto;
        gap: .35rem;
        opacity: .95;
    }
    .market-seeding-dark-skin .market-seeding-fit-panel,
    .market-seeding-dark-skin .market-seeding-fit-ship,
    .market-seeding-dark-skin .market-seeding-fit-slot-group {
        border-color: rgba(97, 140, 153, .45);
    }
    .market-seeding-dark-skin .market-seeding-fit-slot-group {
        background: transparent;
    }
</style>
