<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStockStatusToMarketSeedingItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('seat_market_seeding_items', function (Blueprint $table) {
            $table->string('stock_status', 20)->nullable()->after('warning_quantity');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('seat_market_seeding_items', function (Blueprint $table) {
            $table->dropColumn('stock_status');
        });
    }
}
