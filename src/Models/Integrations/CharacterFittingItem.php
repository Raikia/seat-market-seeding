<?php

namespace Raikia\SeatMarketSeeding\Models\Integrations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Seat\Eveapi\Models\Sde\InvType;

class CharacterFittingItem extends Model
{
    protected $table = 'character_fitting_items';

    protected $guarded = [];

    public function fitting(): BelongsTo
    {
        return $this->belongsTo(CharacterFitting::class, 'fitting_id', 'fitting_id');
    }

    public function type(): HasOne
    {
        return $this->hasOne(InvType::class, 'typeID', 'type_id');
    }
}
