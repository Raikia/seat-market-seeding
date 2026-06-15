<?php

return [
    'market_seeding_low_stock' => [
        'label' => 'seat-market-seeding::alerts.low_stock',
        'handlers' => [
            'mail' => \Raikia\SeatMarketSeeding\Notifications\MarketStock\Mail\MarketStockTransition::class,
            'slack' => \Raikia\SeatMarketSeeding\Notifications\MarketStock\Slack\MarketStockTransition::class,
            'discord' => \Raikia\SeatMarketSeeding\Notifications\MarketStock\Discord\MarketStockTransition::class,
        ],
    ],
    'market_seeding_empty_stock' => [
        'label' => 'seat-market-seeding::alerts.empty_stock',
        'handlers' => [
            'mail' => \Raikia\SeatMarketSeeding\Notifications\MarketStock\Mail\MarketStockTransition::class,
            'slack' => \Raikia\SeatMarketSeeding\Notifications\MarketStock\Slack\MarketStockTransition::class,
            'discord' => \Raikia\SeatMarketSeeding\Notifications\MarketStock\Discord\MarketStockTransition::class,
        ],
    ],
];
