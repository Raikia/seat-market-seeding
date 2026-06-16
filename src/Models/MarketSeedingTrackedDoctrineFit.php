<?php

namespace Raikia\SeatMarketSeeding\Models;

use Illuminate\Database\Eloquent\Model;

class MarketSeedingTrackedDoctrineFit extends Model
{
    protected $table = 'seat_market_seeding_tracked_doctrine_fits';

    protected $fillable = [
        'tracked_doctrine_id',
        'fitting_id',
        'fitting_name',
        'ship_type_id',
        'ship_type_name',
        'ship_multiplier',
        'fitting_multiplier',
    ];

    protected $casts = [
        'tracked_doctrine_id' => 'integer',
        'fitting_id' => 'integer',
        'ship_type_id' => 'integer',
        'ship_multiplier' => 'integer',
        'fitting_multiplier' => 'integer',
    ];

    public function trackedDoctrine()
    {
        return $this->belongsTo(MarketSeedingTrackedDoctrine::class, 'tracked_doctrine_id');
    }
}
