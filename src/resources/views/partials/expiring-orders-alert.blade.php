@php
    $expiringOrders = $marketSeedingExpiringOrders ?? ['count' => 0, 'orders' => collect(), 'days' => 7, 'total_value' => 0];
    $expiringOrderRows = collect($expiringOrders['orders'] ?? []);
    $expiringOrderCount = (int) ($expiringOrders['count'] ?? 0);
    $expiringOrderDays = (int) ($expiringOrders['days'] ?? 7);
    $expiringOrderIsk = fn ($value) => number_format((float) $value, 2, '.', ',') . ' ISK';
    $expiringOrderWhole = fn ($value) => number_format((float) $value, 0, '.', ',');
@endphp

@if($expiringOrderCount > 0)
    <style>
        .market-seeding-expiring-orders-alert {
            border: 1px solid rgba(217, 164, 6, .45);
            border-radius: 8px;
            margin-bottom: 1rem;
            padding: .9rem 1rem;
        }
        .market-seeding-expiring-orders-alert .expiring-orders-title {
            align-items: center;
            display: flex;
            gap: .45rem;
            font-weight: 700;
            margin-bottom: .35rem;
        }
        .market-seeding-expiring-orders-alert .expiring-orders-summary {
            margin-bottom: .65rem;
        }
        .market-seeding-expiring-orders-alert .expiring-orders-list {
            display: grid;
            gap: .4rem;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            margin: 0;
            padding: 0;
        }
        .market-seeding-expiring-orders-alert .expiring-orders-item {
            background: rgba(255, 255, 255, .35);
            border: 1px solid rgba(217, 164, 6, .25);
            border-radius: 8px;
            list-style: none;
            padding: .55rem .65rem;
        }
        .market-seeding-expiring-orders-alert .expiring-orders-item strong,
        .market-seeding-expiring-orders-alert .expiring-orders-item span {
            display: block;
        }
        .market-seeding-expiring-orders-alert .expiring-orders-meta {
            font-size: .82rem;
            opacity: .82;
        }
        .market-seeding-dark-skin .market-seeding-expiring-orders-alert .expiring-orders-item {
            background: rgba(255, 193, 7, .08);
            border-color: rgba(244, 231, 190, .18);
        }
    </style>

    <div class="alert alert-warning market-seeding-expiring-orders-alert">
        <div class="expiring-orders-title">
            <i class="fas fa-hourglass-half"></i>
            <span>Your market orders are expiring soon</span>
        </div>
        <div class="expiring-orders-summary">
            {{ $expiringOrderWhole($expiringOrderCount) }} tracked sell order{{ $expiringOrderCount === 1 ? '' : 's' }}
            from your characters expire within {{ $expiringOrderWhole($expiringOrderDays) }} days.
            Listed value: {{ $expiringOrderIsk($expiringOrders['total_value'] ?? 0) }}.
        </div>
        <ul class="expiring-orders-list">
            @foreach($expiringOrderRows->take(6) as $order)
                <li class="expiring-orders-item">
                    <strong>{{ $order['type_name'] }}</strong>
                    <span class="expiring-orders-meta">
                        {{ $order['character_name'] }} &bull; {{ $order['market_name'] }}
                    </span>
                    <span class="expiring-orders-meta">
                        {{ $expiringOrderWhole($order['quantity_remaining']) }} remaining &bull;
                        {{ $expiringOrderIsk($order['price']) }} each &bull;
                        expires {{ $order['expires_at'] }} ({{ $expiringOrderWhole($order['days_until_expiry']) }}d)
                    </span>
                </li>
            @endforeach
            @if($expiringOrderCount > 6)
                <li class="expiring-orders-item">
                    <strong>+{{ $expiringOrderWhole($expiringOrderCount - 6) }} more</strong>
                    <span class="expiring-orders-meta">Open the Seeders page or item details to review the full order list.</span>
                </li>
            @endif
        </ul>
    </div>
@endif
