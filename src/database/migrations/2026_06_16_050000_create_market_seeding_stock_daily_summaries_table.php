<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('seat_market_seeding_stock_daily_summaries')) {
            return;
        }

        Schema::create('seat_market_seeding_stock_daily_summaries', function (Blueprint $table) {
            $table->increments('id');
            $table->date('summary_date');
            $table->unsignedInteger('market_id')->nullable();
            $table->unsignedInteger('item_id')->nullable();
            $table->unsignedInteger('role_id')->nullable();
            $table->bigInteger('type_id')->unsigned();
            $table->string('market_name');
            $table->string('location_name');
            $table->string('type_name');
            $table->string('type_category')->default('Unknown');
            $table->unsignedInteger('estimated_sold_quantity')->default(0);
            $table->unsignedInteger('restocked_quantity')->default(0);
            $table->unsignedInteger('sales_events')->default(0);
            $table->unsignedInteger('low_events')->default(0);
            $table->unsignedInteger('empty_events')->default(0);
            $table->unsignedInteger('stocked_events')->default(0);
            $table->unsignedInteger('total_shortage')->default(0);
            $table->unsignedInteger('latest_current_quantity')->default(0);
            $table->unsignedInteger('latest_desired_quantity')->default(0);
            $table->unsignedInteger('latest_warning_quantity')->default(0);
            $table->timestamp('last_sold_at')->nullable();
            $table->timestamp('last_needed_at')->nullable();
            $table->timestamps();

            $table->unique(['summary_date', 'market_id', 'item_id', 'type_id'], 'sms_daily_unique');
            $table->index(['market_id', 'summary_date'], 'sms_daily_market_date_idx');
            $table->index(['item_id', 'summary_date'], 'sms_daily_item_date_idx');
            $table->index(['type_category', 'summary_date'], 'sms_daily_category_date_idx');
            $table->index(['estimated_sold_quantity', 'summary_date'], 'sms_daily_sold_date_idx');
        });

        $this->backfillFromSnapshots();
        $this->backfillFromHistory();
    }

    public function down()
    {
        Schema::dropIfExists('seat_market_seeding_stock_daily_summaries');
    }

    private function backfillFromSnapshots(): void
    {
        if (!Schema::hasTable('seat_market_seeding_stock_snapshots')) {
            return;
        }

        DB::statement("
            INSERT INTO seat_market_seeding_stock_daily_summaries (
                summary_date,
                market_id,
                item_id,
                role_id,
                type_id,
                market_name,
                location_name,
                type_name,
                type_category,
                estimated_sold_quantity,
                restocked_quantity,
                sales_events,
                latest_current_quantity,
                latest_desired_quantity,
                latest_warning_quantity,
                last_sold_at,
                created_at,
                updated_at
            )
            SELECT
                DATE(created_at) AS summary_date,
                market_id,
                item_id,
                MAX(role_id) AS role_id,
                type_id,
                MAX(market_name) AS market_name,
                MAX(location_name) AS location_name,
                MAX(type_name) AS type_name,
                MAX(type_category) AS type_category,
                SUM(estimated_sold_quantity) AS estimated_sold_quantity,
                SUM(restocked_quantity) AS restocked_quantity,
                SUM(CASE WHEN estimated_sold_quantity > 0 THEN 1 ELSE 0 END) AS sales_events,
                SUBSTRING_INDEX(GROUP_CONCAT(current_quantity ORDER BY created_at DESC), ',', 1) AS latest_current_quantity,
                SUBSTRING_INDEX(GROUP_CONCAT(desired_quantity ORDER BY created_at DESC), ',', 1) AS latest_desired_quantity,
                SUBSTRING_INDEX(GROUP_CONCAT(warning_quantity ORDER BY created_at DESC), ',', 1) AS latest_warning_quantity,
                MAX(CASE WHEN estimated_sold_quantity > 0 THEN created_at ELSE NULL END) AS last_sold_at,
                NOW(),
                NOW()
            FROM seat_market_seeding_stock_snapshots
            GROUP BY DATE(created_at), market_id, item_id, type_id
        ");
    }

    private function backfillFromHistory(): void
    {
        if (!Schema::hasTable('seat_market_seeding_stock_history')) {
            return;
        }

        DB::statement("
            INSERT INTO seat_market_seeding_stock_daily_summaries (
                summary_date,
                market_id,
                item_id,
                role_id,
                type_id,
                market_name,
                location_name,
                type_name,
                type_category,
                low_events,
                empty_events,
                stocked_events,
                total_shortage,
                latest_current_quantity,
                latest_desired_quantity,
                latest_warning_quantity,
                last_needed_at,
                created_at,
                updated_at
            )
            SELECT
                DATE(created_at) AS summary_date,
                market_id,
                item_id,
                MAX(role_id) AS role_id,
                type_id,
                MAX(market_name) AS market_name,
                MAX(location_name) AS location_name,
                MAX(type_name) AS type_name,
                'Unknown' AS type_category,
                SUM(CASE WHEN current_status = 'low' THEN 1 ELSE 0 END) AS low_events,
                SUM(CASE WHEN current_status = 'empty' THEN 1 ELSE 0 END) AS empty_events,
                SUM(CASE WHEN current_status = 'stocked' THEN 1 ELSE 0 END) AS stocked_events,
                SUM(CASE WHEN current_status IN ('low', 'empty') THEN GREATEST(desired_quantity - current_quantity, 0) ELSE 0 END) AS total_shortage,
                SUBSTRING_INDEX(GROUP_CONCAT(current_quantity ORDER BY created_at DESC), ',', 1) AS latest_current_quantity,
                SUBSTRING_INDEX(GROUP_CONCAT(desired_quantity ORDER BY created_at DESC), ',', 1) AS latest_desired_quantity,
                SUBSTRING_INDEX(GROUP_CONCAT(warning_quantity ORDER BY created_at DESC), ',', 1) AS latest_warning_quantity,
                MAX(CASE WHEN current_status IN ('low', 'empty') THEN created_at ELSE NULL END) AS last_needed_at,
                NOW(),
                NOW()
            FROM seat_market_seeding_stock_history
            GROUP BY DATE(created_at), market_id, item_id, type_id
            ON DUPLICATE KEY UPDATE
                low_events = VALUES(low_events),
                empty_events = VALUES(empty_events),
                stocked_events = VALUES(stocked_events),
                total_shortage = VALUES(total_shortage),
                last_needed_at = VALUES(last_needed_at),
                updated_at = NOW()
        ");
    }
};
