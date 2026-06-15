<?php

namespace Raikia\SeatMarketSeeding\Models;

use Illuminate\Database\Eloquent\Model;
use Seat\Eveapi\Models\Sde\InvType;

class SeededMarketItem extends Model
{
    protected $table = 'seat_market_seeding_items';

    protected $fillable = [
        'market_id',
        'type_id',
        'type_name',
        'desired_quantity',
        'warning_quantity',
        'stock_status',
        'notes',
    ];

    protected $casts = [
        'type_id' => 'integer',
        'desired_quantity' => 'integer',
        'warning_quantity' => 'integer',
    ];

    public function market()
    {
        return $this->belongsTo(SeededMarket::class, 'market_id');
    }

    public function type()
    {
        return $this->hasOne(InvType::class, 'typeID', 'type_id');
    }
}
