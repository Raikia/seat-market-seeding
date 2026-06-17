@extends('web::layouts.grids.12')

@section('title', 'Market Seeding Restock History')
@section('page_header', 'Market Seeding Restock History')

@section('content')
    @php
        $activeSkin = setting('skin') ?: 'default';
        $marketSeedingThemeClass = in_array($activeSkin, ['jet', 'iuligigi', 'gigigraphite'], true)
            ? 'market-seeding-dark-skin'
            : '';
        $whole = function ($value) {
            return number_format((float) $value, 0, '.', ',');
        };
        $statusBadge = function ($status) {
            return [
                'stocked' => 'badge-success',
                'low' => 'badge-warning',
                'empty' => 'badge-danger',
            ][$status] ?? 'badge-secondary';
        };
    @endphp

    <style>
        .market-seeding-history-shell .card-header {
            align-items: center;
            display: flex;
            gap: 1rem;
            justify-content: space-between;
        }
        .market-seeding-history-shell .card-title {
            float: none;
        }
        .market-seeding-history-shell .history-filters {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
        }
        .market-seeding-history-shell .history-filters .form-control {
            min-width: 220px;
        }
        .market-seeding-history-chart {
            height: 260px;
            margin-bottom: 1rem;
            position: relative;
        }
        .market-seeding-restock-leaders {
            margin-bottom: 1rem;
        }
        .market-seeding-restock-leaders .table {
            margin-bottom: 0;
        }
        .market-seeding-dark-skin .card,
        .market-seeding-dark-skin .card-header,
        .market-seeding-dark-skin .card-body {
            background: #222d32;
            border-color: #3c4b54;
            color: #e9ecef;
        }
        .market-seeding-dark-skin .text-muted {
            color: #b8c7ce !important;
        }
        .market-seeding-dark-skin .table {
            color: #e9ecef;
        }
        .market-seeding-dark-skin .table thead th,
        .market-seeding-dark-skin .table td {
            border-color: #3c4b54;
        }
    </style>

    <div class="market-seeding-history-shell {{ $marketSeedingThemeClass }}">
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title mb-0">Restock History</h3>
                    <small class="text-muted">Stock status transitions recorded during ESI refreshes.</small>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('market-seeding.history') }}" class="history-filters mb-3">
                    <select name="market_id" class="form-control">
                        <option value="">All Markets</option>
                        @foreach($markets as $market)
                            <option value="{{ $market->id }}" {{ request('market_id') == $market->id ? 'selected' : '' }}>
                                {{ $market->name }}
                            </option>
                        @endforeach
                    </select>
                    <select name="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="low" {{ request('status') === 'low' ? 'selected' : '' }}>Low</option>
                        <option value="empty" {{ request('status') === 'empty' ? 'selected' : '' }}>Empty</option>
                        <option value="stocked" {{ request('status') === 'stocked' ? 'selected' : '' }}>Recovered / Stocked</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('market-seeding.history') }}" class="btn btn-default">Reset</a>
                </form>

                <div class="market-seeding-history-chart">
                    <canvas id="market-seeding-history-chart"></canvas>
                </div>

                <div class="card market-seeding-restock-leaders">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title mb-0">Most Frequent Restock Needs</h3>
                            <small class="text-muted">Items that most often moved into low or empty status{{ request('market_id') ? ' for the selected market' : '' }}.</small>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Market</th>
                                        <th class="text-right">Restock Events</th>
                                        <th class="text-right">Empty</th>
                                        <th class="text-right">Low</th>
                                        <th class="text-right">Total Shortage Seen</th>
                                        <th>Last Needed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($restockLeaders as $leader)
                                        <tr>
                                            <td>{{ $leader->type_name }}</td>
                                            <td>
                                                {{ $leader->market_name }}
                                                <div class="text-muted small">{{ $leader->location_name }}</div>
                                            </td>
                                            <td class="text-right" data-order="{{ $leader->restock_events }}">{{ $whole($leader->restock_events) }}</td>
                                            <td class="text-right" data-order="{{ $leader->empty_events }}">
                                                <span class="badge badge-danger">{{ $whole($leader->empty_events) }}</span>
                                            </td>
                                            <td class="text-right" data-order="{{ $leader->low_events }}">
                                                <span class="badge badge-warning">{{ $whole($leader->low_events) }}</span>
                                            </td>
                                            <td class="text-right" data-order="{{ $leader->total_shortage }}">{{ $whole($leader->total_shortage) }}</td>
                                            <td data-order="{{ $leader->last_needed_at ? \Carbon\Carbon::parse($leader->last_needed_at)->timestamp : 0 }}">
                                                {{ $leader->last_needed_at ? \Carbon\Carbon::parse($leader->last_needed_at)->format('Y-m-d H:i') : '-' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-muted">No low or empty restock events have been recorded yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover market-seeding-history-table">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Market</th>
                                <th>Item</th>
                                <th>Status</th>
                                <th class="text-right">Current</th>
                                <th class="text-right">Warning</th>
                                <th class="text-right">Target</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($history as $event)
                                <tr>
                                    <td data-order="{{ optional($event->created_at)->timestamp }}">{{ optional($event->created_at)->format('Y-m-d H:i') }}</td>
                                    <td>
                                        {{ $event->market_name }}
                                        <div class="text-muted small">{{ $event->location_name }}</div>
                                    </td>
                                    <td>{{ $event->type_name }}</td>
                                    <td>
                                        <span class="badge {{ $statusBadge($event->current_status) }}">
                                            {{ ucfirst($event->current_status) }}
                                        </span>
                                        @if($event->previous_status)
                                            <span class="text-muted small">{{ $event->previous_status }} &rarr; {{ $event->current_status }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right" data-order="{{ $event->current_quantity }}">{{ $whole($event->current_quantity) }}</td>
                                    <td class="text-right" data-order="{{ $event->warning_quantity }}">{{ $whole($event->warning_quantity) }}</td>
                                    <td class="text-right" data-order="{{ $event->desired_quantity }}">{{ $whole($event->desired_quantity) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($history->isEmpty())
                    <p class="text-muted mb-0">No stock transitions have been recorded yet.</p>
                @endif

                {{ $history->links() }}
            </div>
        </div>
    </div>
@endsection

@push('javascript')
    <script>
        $(function () {
            var chartData = @json($chartData);

            if (window.Chart && document.getElementById('market-seeding-history-chart')) {
                new Chart(document.getElementById('market-seeding-history-chart'), {
                    type: 'bar',
                    data: {
                        labels: chartData.labels || [],
                        datasets: [
                            {
                                label: 'Low',
                                data: (chartData.series || {}).low || [],
                                backgroundColor: 'rgba(255, 193, 7, .75)'
                            },
                            {
                                label: 'Empty',
                                data: (chartData.series || {}).empty || [],
                                backgroundColor: 'rgba(220, 53, 69, .75)'
                            },
                            {
                                label: 'Recovered',
                                data: (chartData.series || {}).stocked || [],
                                backgroundColor: 'rgba(40, 167, 69, .75)'
                            }
                        ]
                    },
                    options: {
                        maintainAspectRatio: false,
                        responsive: true,
                        scales: {
                            xAxes: [{
                                stacked: true
                            }],
                            yAxes: [{
                                stacked: true,
                                ticks: {
                                    beginAtZero: true,
                                    precision: 0
                                }
                            }]
                        },
                        legend: {
                            position: 'bottom'
                        },
                        title: {
                            display: true,
                            text: 'Stock Transitions, Last 30 Days'
                        }
                    }
                });
            }

            if ($.fn.DataTable) {
                $('.market-seeding-history-table').DataTable({
                    order: [[0, 'desc']],
                    paging: false,
                    searching: true,
                    info: false,
                    autoWidth: false,
                    language: {
                        emptyTable: 'No stock transitions have been recorded yet.',
                        zeroRecords: 'No history entries match this search.'
                    }
                });
            }
        });
    </script>
@endpush
