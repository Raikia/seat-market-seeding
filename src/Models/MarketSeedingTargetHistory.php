<?php

namespace Raikia\SeatMarketSeeding\Models;

use Illuminate\Database\Eloquent\Model;

class MarketSeedingTargetHistory extends Model
{
    public const CHANGE_MANUAL = 'manual';
    public const CHANGE_BULK_IMPORT = 'bulk_import';
    public const CHANGE_SAVED_FITTING = 'saved_fitting';
    public const CHANGE_DOCTRINE = 'doctrine';
    public const CHANGE_RECOMMENDATION = 'recommendation';
    public const CHANGE_CLEAR = 'clear';
    public const CHANGE_SYSTEM = 'system';

    protected $table = 'seat_market_seeding_target_histories';

    protected $fillable = [
        'market_id',
        'item_id',
        'type_id',
        'market_name',
        'location_name',
        'type_name',
        'old_target_quantity',
        'new_target_quantity',
        'old_warning_quantity',
        'new_warning_quantity',
        'change_type',
        'user_id',
        'user_name',
    ];

    protected $casts = [
        'market_id' => 'integer',
        'item_id' => 'integer',
        'type_id' => 'integer',
        'old_target_quantity' => 'integer',
        'new_target_quantity' => 'integer',
        'old_warning_quantity' => 'integer',
        'new_warning_quantity' => 'integer',
        'user_id' => 'integer',
    ];

    public function market()
    {
        return $this->belongsTo(SeededMarket::class, 'market_id');
    }

    public function item()
    {
        return $this->belongsTo(SeededMarketItem::class, 'item_id');
    }

    public function changeTypeLabel(): string
    {
        return [
            self::CHANGE_MANUAL => 'Manual edit',
            self::CHANGE_BULK_IMPORT => 'Bulk import',
            self::CHANGE_SAVED_FITTING => 'Saved fit import',
            self::CHANGE_DOCTRINE => 'Doctrine sync',
            self::CHANGE_RECOMMENDATION => 'Recommendation',
            self::CHANGE_CLEAR => 'Market clear',
            self::CHANGE_SYSTEM => 'System recalculation',
        ][$this->change_type] ?? ucfirst(str_replace('_', ' ', (string) $this->change_type));
    }
}
