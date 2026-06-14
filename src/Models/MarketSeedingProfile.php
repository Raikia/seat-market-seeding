<?php

namespace Raikia\SeatMarketSeeding\Models;

use Illuminate\Database\Eloquent\Model;

class MarketSeedingProfile extends Model
{
    protected $table = 'seat_market_seeding_profiles';

    protected $fillable = [
        'name',
        'description',
        'stock_list',
    ];
}
