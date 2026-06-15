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
    ];

    protected $casts = [
        'location_id' => 'integer',
        'sort_order' => 'integer',
        'region_id' => 'integer',
        'solar_system_id' => 'integer',
        'is_structure' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(SeededMarketItem::class, 'market_id');
    }

    public function role()
    {
        return $this->belongsTo(\Seat\Web\Models\Acl\Role::class, 'role_id');
    }
}
