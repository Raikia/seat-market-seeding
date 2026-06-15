<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateMarketSeedingSettingsTable extends Migration
{
    public function up()
    {
        Schema::create('seat_market_seeding_settings', function (Blueprint $table) {
            $table->string('setting')->primary();
            $table->string('value')->nullable();
            $table->timestamps();
        });

        DB::table('seat_market_seeding_settings')->insert([
            'setting' => 'history_retention_days',
            'value' => '365',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('seat_market_seeding_settings');
    }
}
