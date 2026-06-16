<?php

namespace Raikia\SeatMarketSeeding\Models\Integrations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Seat\Eveapi\Models\Sde\InvType;
use Seat\Eveapi\Models\Universe\UniverseName;

class CharacterFitting extends Model
{
    protected $table = 'character_fittings';

    protected $guarded = [];

    public function items(): HasMany
    {
        return $this->hasMany(CharacterFittingItem::class, 'fitting_id', 'fitting_id');
    }

    public function shipType(): HasOne
    {
        return $this->hasOne(InvType::class, 'typeID', 'ship_type_id');
    }

    public function characterName(): HasOne
    {
        return $this->hasOne(UniverseName::class, 'entity_id', 'character_id');
    }
}
