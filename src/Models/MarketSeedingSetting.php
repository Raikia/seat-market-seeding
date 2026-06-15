<?php

namespace Raikia\SeatMarketSeeding\Models;

use Illuminate\Database\Eloquent\Model;

class MarketSeedingSetting extends Model
{
    protected $table = 'seat_market_seeding_settings';

    protected $primaryKey = 'setting';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'setting',
        'value',
    ];
}
