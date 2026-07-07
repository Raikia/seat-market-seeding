<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRefreshCharacterToMarketSeedingMarkets extends Migration
{
    public function up()
    {
        Schema::table('seat_market_seeding_markets', function (Blueprint $table) {
            $table->bigInteger('refresh_character_id')->unsigned()->nullable()->after('role_id')->index();
            $table->string('refresh_character_name')->nullable()->after('refresh_character_id');
        });
    }

    public function down()
    {
        Schema::table('seat_market_seeding_markets', function (Blueprint $table) {
            $table->dropIndex(['refresh_character_id']);
            $table->dropColumn(['refresh_character_id', 'refresh_character_name']);
        });
    }
}
