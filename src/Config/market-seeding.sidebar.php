<?php

return [
    'market-seeding' => [
        'name' => 'Market Seeding',
        'label' => 'seat-market-seeding::sidebar.market_seeding',
        'icon' => 'fas fa-store',
        'route_segment' => 'market-seeding',
        'permission' => 'seat-market-seeding.view',
        'entries' => [
            [
                'name' => 'Dashboard',
                'label' => 'seat-market-seeding::sidebar.dashboard',
                'icon' => 'fas fa-chart-line',
                'route' => 'market-seeding.index',
                'permission' => 'seat-market-seeding.view',
            ],
            [
                'name' => 'History',
                'label' => 'seat-market-seeding::sidebar.history',
                'icon' => 'fas fa-history',
                'route' => 'market-seeding.history',
                'permission' => 'seat-market-seeding.view',
            ],
            [
                'name' => 'Seeders',
                'label' => 'seat-market-seeding::sidebar.seeders',
                'icon' => 'fas fa-trophy',
                'route' => 'market-seeding.seeders',
                'permission' => 'seat-market-seeding.seeders',
            ],
            [
                'name' => 'Settings',
                'label' => 'seat-market-seeding::sidebar.settings',
                'icon' => 'fas fa-cog',
                'route' => 'market-seeding.settings',
                'permission' => 'seat-market-seeding.manager',
            ],
        ],
    ],
];
