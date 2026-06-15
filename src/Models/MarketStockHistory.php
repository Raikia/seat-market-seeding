<?php

namespace Raikia\SeatMarketSeeding\Models;

use Illuminate\Database\Eloquent\Model;

class MarketStockHistory extends Model
{
    protected $table = 'seat_market_seeding_stock_history';

    protected $fillable = [
        'market_id',
        'item_id',
        'type_id',
        'market_name',
        'location_name',
        'type_name',
        'previous_status',
        'current_status',
        'current_quantity',
        'warning_quantity',
        'desired_quantity',
    ];

    protected $casts = [
        'market_id' => 'integer',
        'item_id' => 'integer',
        'type_id' => 'integer',
        'current_quantity' => 'integer',
        'warning_quantity' => 'integer',
        'desired_quantity' => 'integer',
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
