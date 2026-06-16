<?php

namespace Raikia\SeatMarketSeeding\Models;

use Illuminate\Database\Eloquent\Model;

class MarketSeedingTrackedDoctrine extends Model
{
    const MERGE_MAX = 'max';
    const MERGE_ADD = 'add';
    const FIT_AGGREGATION_MAX = 'max';
    const FIT_AGGREGATION_SUM = 'sum';

    protected $table = 'seat_market_seeding_tracked_doctrines';

    protected $fillable = [
        'market_id',
        'doctrine_id',
        'doctrine_name',
        'multiplier',
        'warning_percentage',
        'merge_mode',
        'fit_aggregation_mode',
        'last_synced_at',
        'last_sync_status',
        'last_sync_message',
    ];

    protected $casts = [
        'market_id' => 'integer',
        'doctrine_id' => 'integer',
        'multiplier' => 'integer',
        'warning_percentage' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    public function market()
    {
        return $this->belongsTo(SeededMarket::class, 'market_id');
    }

    public function sources()
    {
        return $this->hasMany(MarketSeedingItemSource::class, 'tracked_doctrine_id');
    }

    public function fitSettings()
    {
        return $this->hasMany(MarketSeedingTrackedDoctrineFit::class, 'tracked_doctrine_id');
    }
}
