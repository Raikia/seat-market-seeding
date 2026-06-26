<?php

namespace Raikia\SeatMarketSeeding\Models;

use Illuminate\Database\Eloquent\Model;

class MarketStockDailySummary extends Model
{
    protected $table = 'seat_market_seeding_stock_daily_summaries';

    protected $fillable = [
        'summary_date',
        'market_id',
        'item_id',
        'role_id',
        'type_id',
        'market_name',
        'location_name',
        'type_name',
        'type_category',
        'estimated_sold_quantity',
        'restocked_quantity',
        'sales_events',
        'low_events',
        'empty_events',
        'stocked_events',
        'total_shortage',
        'latest_current_quantity',
        'latest_desired_quantity',
        'latest_warning_quantity',
        'last_sold_at',
        'last_needed_at',
    ];

    protected $casts = [
        'summary_date' => 'date',
        'market_id' => 'integer',
        'item_id' => 'integer',
        'role_id' => 'integer',
        'type_id' => 'integer',
        'estimated_sold_quantity' => 'integer',
        'restocked_quantity' => 'integer',
        'sales_events' => 'integer',
        'low_events' => 'integer',
        'empty_events' => 'integer',
        'stocked_events' => 'integer',
        'total_shortage' => 'integer',
        'latest_current_quantity' => 'integer',
        'latest_desired_quantity' => 'integer',
        'latest_warning_quantity' => 'integer',
        'last_sold_at' => 'datetime',
        'last_needed_at' => 'datetime',
    ];

    public function market()
    {
        return $this->belongsTo(SeededMarket::class, 'market_id');
    }

    public function item()
    {
        return $this->belongsTo(SeededMarketItem::class, 'item_id');
    }
}
