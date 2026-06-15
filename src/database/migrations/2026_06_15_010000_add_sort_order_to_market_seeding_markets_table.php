<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSortOrderToMarketSeedingMarketsTable extends Migration
{
    public function up()
    {
        Schema::table('seat_market_seeding_markets', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('id');
        });
    }

    public function down()
    {
        Schema::table('seat_market_seeding_markets', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
}
