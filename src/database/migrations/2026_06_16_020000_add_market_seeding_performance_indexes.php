<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('market_orders') && !$this->indexExists('market_orders', 'sms_market_orders_location_buy_type_idx')) {
            Schema::table('market_orders', function (Blueprint $table) {
                $table->index(['location_id', 'is_buy_order', 'type_id'], 'sms_market_orders_location_buy_type_idx');
            });
        }

        if (Schema::hasTable('seat_market_seeding_markets') && !$this->indexExists('seat_market_seeding_markets', 'sms_markets_role_sort_name_idx')) {
            Schema::table('seat_market_seeding_markets', function (Blueprint $table) {
                $table->index(['role_id', 'sort_order', 'name'], 'sms_markets_role_sort_name_idx');
            });
        }

        if (Schema::hasTable('seat_market_seeding_stock_history') && !$this->indexExists('seat_market_seeding_stock_history', 'sms_hist_status_created_idx')) {
            Schema::table('seat_market_seeding_stock_history', function (Blueprint $table) {
                $table->index(['current_status', 'created_at'], 'sms_hist_status_created_idx');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('seat_market_seeding_markets') && $this->indexExists('seat_market_seeding_markets', 'sms_markets_role_sort_name_idx')) {
            Schema::table('seat_market_seeding_markets', function (Blueprint $table) {
                $table->dropIndex('sms_markets_role_sort_name_idx');
            });
        }

        if (Schema::hasTable('market_orders') && $this->indexExists('market_orders', 'sms_market_orders_location_buy_type_idx')) {
            Schema::table('market_orders', function (Blueprint $table) {
                $table->dropIndex('sms_market_orders_location_buy_type_idx');
            });
        }

        if (Schema::hasTable('seat_market_seeding_stock_history') && $this->indexExists('seat_market_seeding_stock_history', 'sms_hist_status_created_idx')) {
            Schema::table('seat_market_seeding_stock_history', function (Blueprint $table) {
                $table->dropIndex('sms_hist_status_created_idx');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return !empty(DB::select('SHOW INDEX FROM `' . $table . '` WHERE Key_name = ?', [$index]));
    }
};
