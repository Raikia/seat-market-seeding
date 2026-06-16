# SeAT Market Seeding

Market Seeding is a SeAT plugin for keeping an eye on stocked markets in EVE Online. It lets you define what should be available at each station or structure, compare that target against current market orders, and quickly build a restock list when things run low.

The goal is simple: make it easier to answer “what is missing from this market?” without maintaining a spreadsheet by hand.

## Installation

For a standard SeAT Docker install, add the plugin package to the `PLUGINS` variable in your `.env` file:

```env
PLUGINS=raikia/seat-market-seeding
```

If you already have other plugins listed, add it to the same variable with the same separator style your SeAT install is using.

After updating `.env`, restart/update your SeAT containers the same way you normally do for plugin changes, then run SeAT migrations if your setup does not run them automatically.

## Features

- Track multiple seeded markets by station or structure.
- Set target quantities and low-stock warning levels per item.
- Add items one at a time, by bulk paste, from EFT-style fittings, from saved fits, or from reusable market profiles.
- Preview imports before applying them.
- Export missing items in a copy/paste friendly EVE multi-buy format.
- Show estimated Jita restock cost and packaged restock volume.
- Show seeded value, target value, missing lines, restock cost, and per-market health.
- Compare local market prices against Jita prices.
- Track source of each item target, including manual adds, doctrine tracking, or both.
- Auto-track Seat-Fitting doctrines with separate ship and fitting multipliers per fit.
- Choose whether doctrine fits are summed together or use the maximum requirement per item.
- Review doctrine sync changes before applying them.
- Keep restock history and stock transition history.
- Send SeAT notifications when an item moves from stocked to low, or from low/stocked to empty.
- Refresh market data manually or on a SeAT schedule.
- Cache dashboard calculations briefly and add helpful indexes for larger market lists.

## Seat-Fitting Integration

If `eveseat-plugins/seat-fitting` is installed, Market Seeding can use its saved fits and doctrines.

Doctrine tracking is per market. When a doctrine is tracked, the plugin can sync its fits into that market’s target list. If a doctrine changes later, the scheduled refresh can sync those changes so removed doctrine items are removed from the doctrine portion of the target.

Each tracked doctrine can be tuned per fit. You can stock fewer hulls than modules, give support ships a smaller multiplier than mainline ships, and choose whether duplicated modules across fits are summed or use the largest individual fit requirement.

Manual targets are kept separate from doctrine targets. For example, if you manually track 50 Warp Scrambler IIs and a doctrine adds 1 more, removing that doctrine will not remove the manually tracked amount.

## Settings

Managers can configure the plugin from the Market Seeding settings page.

Common setup steps:

1. Create a market for the station or structure you want to track.
2. Add target items manually, through bulk import, from saved fits, or by tracking a doctrine.
3. Refresh ESI market data.
4. Use the dashboard to review shortages, prices, health, and restock exports.

The settings page also includes reusable market profiles, restock history retention, and maintenance actions such as clearing restock history.

## Dashboard and History

The dashboard is meant for day-to-day restocking. It shows each market’s health, local quantity, target quantity, missing quantity, local price, Jita price, restock cost, and restock volume. Restock lists can be copied directly into EVE multi-buy.

The restock history page keeps a record of stock state changes, with filters and a small chart for recent stocked, low, and empty events.

## Permissions

The plugin has a manager permission for configuration. Users with view access can see markets they are allowed to see, while managers can create markets, edit targets, manage profiles, and refresh data.

## Notes

Market data depends on what SeAT can retrieve from ESI and what orders are available for the configured location. Structure access may require the right character/token access in SeAT.

Jita pricing is based on sell orders from Jita 4-4 when available, with SeAT price data used as a fallback.
