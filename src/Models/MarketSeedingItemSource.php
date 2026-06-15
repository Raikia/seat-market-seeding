<?php

namespace Raikia\SeatMarketSeeding\Models;

use Illuminate\Database\Eloquent\Model;

class MarketSeedingItemSource extends Model
{
    const SOURCE_MANUAL = 'manual';
    const SOURCE_MANUAL_ADJUSTMENT = 'manual_adjustment';
    const SOURCE_DOCTRINE = 'doctrine';

    protected $table = 'seat_market_seeding_item_sources';

    protected $fillable = [
        'market_id',
        'item_id',
        'tracked_doctrine_id',
        'source_type',
        'source_key',
        'type_id',
        'type_name',
        'quantity',
        'warning_quantity',
    ];

    protected $casts = [
        'market_id' => 'integer',
        'item_id' => 'integer',
        'tracked_doctrine_id' => 'integer',
        'type_id' => 'integer',
        'quantity' => 'integer',
        'warning_quantity' => 'integer',
    ];

    public function market()
    {
        return $this->belongsTo(SeededMarket::class, 'market_id');
    }

    public function item()
    {
        return $this->belongsTo(SeededMarketItem::class, 'item_id');
    }

    public function trackedDoctrine()
    {
        return $this->belongsTo(MarketSeedingTrackedDoctrine::class, 'tracked_doctrine_id');
    }
}
