<?php

namespace Raikia\SeatMarketSeeding\Models;

use Illuminate\Database\Eloquent\Model;
use Seat\Eveapi\Models\Sde\InvCategory;
use Seat\Eveapi\Models\Sde\InvType;

class SeededMarketItem extends Model
{
    const CATEGORY_LABELS = [
        'Ship' => 'Ships',
        'Module' => 'Modules',
        'Charge' => 'Ammunition & Charges',
        'Drone' => 'Drones',
        'Implant' => 'Implants',
        'Skill' => 'Skills',
        'Commodity' => 'Commodities',
        'Deployable' => 'Deployables',
        'Structure' => 'Structures',
        'Structure Module' => 'Structure Modules',
        'Subsystem' => 'Subsystems',
    ];

    private static array $categoryNames = [];

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

    public function sources()
    {
        return $this->hasMany(MarketSeedingItemSource::class, 'item_id');
    }

    public function targetHistories()
    {
        return $this->hasMany(MarketSeedingTargetHistory::class, 'item_id');
    }

    public function type()
    {
        return $this->hasOne(InvType::class, 'typeID', 'type_id');
    }

    public function sourceFlags(): array
    {
        $sources = $this->relationLoaded('sources')
            ? $this->sources
            : $this->sources()->get();

        return [
            'manual' => $sources->whereIn('source_type', [
                MarketSeedingItemSource::SOURCE_MANUAL,
                MarketSeedingItemSource::SOURCE_MANUAL_ADJUSTMENT,
            ])->isNotEmpty(),
            'doctrine' => $sources->where('source_type', MarketSeedingItemSource::SOURCE_DOCTRINE)->isNotEmpty(),
        ];
    }

    public function typeCategoryName(): string
    {
        $type = $this->relationLoaded('type')
            ? $this->type
            : $this->type()->with('group')->first();

        $categoryId = (int) optional(optional($type)->group)->categoryID;

        if (!$categoryId) {
            return 'Unknown';
        }

        if (!array_key_exists($categoryId, self::$categoryNames)) {
            self::$categoryNames[$categoryId] = optional(InvCategory::where('categoryID', $categoryId)->first())->categoryName ?: 'Unknown';
        }

        return self::CATEGORY_LABELS[self::$categoryNames[$categoryId]] ?? self::$categoryNames[$categoryId];
    }

    public function typeGroupName(): string
    {
        $type = $this->relationLoaded('type')
            ? $this->type
            : $this->type()->with('group')->first();

        return optional(optional($type)->group)->groupName ?: 'Unknown';
    }
}
