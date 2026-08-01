<?php

namespace Raikia\SeatMarketSeeding\Models;

use Illuminate\Database\Eloquent\Model;
use Seat\Notifications\Models\NotificationGroup;

class MarketSeedingNotificationGroupFilter extends Model
{
    protected $table = 'seat_market_seeding_notification_group_filters';

    protected $fillable = [
        'notification_group_id',
        'allowed_market_ids',
    ];

    protected $casts = [
        'allowed_market_ids' => 'array',
    ];

    public function notificationGroup()
    {
        return $this->belongsTo(NotificationGroup::class, 'notification_group_id');
    }
}
