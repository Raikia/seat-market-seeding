<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddRoleIdToMarketSeedingStockHistoryTable extends Migration
{
    public function up()
    {
        Schema::table('seat_market_seeding_stock_history', function (Blueprint $table) {
            $table->unsignedInteger('role_id')->nullable()->after('item_id');
            $table->index(['role_id', 'created_at'], 'sms_hist_role_created_idx');
        });

        DB::table('seat_market_seeding_stock_history')
            ->join('seat_market_seeding_markets', 'seat_market_seeding_stock_history.market_id', '=', 'seat_market_seeding_markets.id')
            ->update([
                'seat_market_seeding_stock_history.role_id' => DB::raw('seat_market_seeding_markets.role_id'),
            ]);
    }

    public function down()
    {
        Schema::table('seat_market_seeding_stock_history', function (Blueprint $table) {
            $table->dropIndex('sms_hist_role_created_idx');
            $table->dropColumn('role_id');
        });
    }
}
