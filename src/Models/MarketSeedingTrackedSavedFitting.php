<?php

namespace Raikia\SeatMarketSeeding\Models;

use Illuminate\Database\Eloquent\Model;

class MarketSeedingTrackedSavedFitting extends Model
{
    const MERGE_MAX = 'max';
    const MERGE_ADD = 'add';

    protected $table = 'seat_market_seeding_tracked_saved_fittings';

    protected $fillable = [
        'market_id',
        'character_id',
        'fitting_id',
        'esi_fitting_id',
        'fitting_name',
        'ship_type_id',
        'ship_type_name',
        'ship_multiplier',
        'fitting_multiplier',
        'warning_percentage',
        'merge_mode',
        'last_synced_at',
        'last_sync_status',
        'last_sync_message',
    ];

    protected $casts = [
        'market_id' => 'integer',
        'character_id' => 'integer',
        'fitting_id' => 'integer',
        'esi_fitting_id' => 'integer',
        'ship_type_id' => 'integer',
        'ship_multiplier' => 'integer',
        'fitting_multiplier' => 'integer',
        'warning_percentage' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    public function market()
    {
        return $this->belongsTo(SeededMarket::class, 'market_id');
    }

    public function sources()
    {
        return $this->hasMany(MarketSeedingItemSource::class, 'tracked_saved_fitting_id');
    }
}
