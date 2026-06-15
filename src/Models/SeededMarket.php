<?php

namespace Raikia\SeatMarketSeeding\Models;

use Illuminate\Database\Eloquent\Model;

class SeededMarket extends Model
{
    protected $table = 'seat_market_seeding_markets';

    protected $fillable = [
        'sort_order',
        'name',
        'location_id',
        'location_name',
        'region_id',
        'solar_system_id',
        'is_structure',
        'role_id',
        'notes',
        'last_refreshed_at',
        'last_refresh_status',
        'last_refresh_message',
        'last_refresh_orders',
    ];

    protected $casts = [
        'location_id' => 'integer',
        'sort_order' => 'integer',
        'region_id' => 'integer',
        'solar_system_id' => 'integer',
        'is_structure' => 'boolean',
        'last_refreshed_at' => 'datetime',
        'last_refresh_orders' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(SeededMarketItem::class, 'market_id');
    }

    public function role()
    {
        return $this->belongsTo(\Seat\Web\Models\Acl\Role::class, 'role_id');
    }

    public function stockHistory()
    {
        return $this->hasMany(MarketStockHistory::class, 'market_id');
    }
}
