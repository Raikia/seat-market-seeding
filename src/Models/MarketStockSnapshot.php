<?php

namespace Raikia\SeatMarketSeeding\Models;

use Illuminate\Database\Eloquent\Model;

class MarketStockSnapshot extends Model
{
    protected $table = 'seat_market_seeding_stock_snapshots';

    protected $fillable = [
        'market_id',
        'item_id',
        'role_id',
        'type_id',
        'market_name',
        'location_name',
        'type_name',
        'type_category',
        'previous_quantity',
        'current_quantity',
        'estimated_sold_quantity',
        'restocked_quantity',
        'warning_quantity',
        'desired_quantity',
    ];

    protected $casts = [
        'market_id' => 'integer',
        'item_id' => 'integer',
        'role_id' => 'integer',
        'type_id' => 'integer',
        'previous_quantity' => 'integer',
        'current_quantity' => 'integer',
        'estimated_sold_quantity' => 'integer',
        'restocked_quantity' => 'integer',
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
