<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRefreshStatusAndHistoryToMarketSeeding extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('seat_market_seeding_markets', 'last_refreshed_at')) {
            Schema::table('seat_market_seeding_markets', function (Blueprint $table) {
                $table->timestamp('last_refreshed_at')->nullable()->after('notes');
                $table->string('last_refresh_status', 20)->nullable()->after('last_refreshed_at');
                $table->text('last_refresh_message')->nullable()->after('last_refresh_status');
                $table->unsignedInteger('last_refresh_orders')->default(0)->after('last_refresh_message');
            });
        }

        if (!Schema::hasTable('seat_market_seeding_stock_history')) {
            Schema::create('seat_market_seeding_stock_history', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('market_id')->nullable();
                $table->unsignedInteger('item_id')->nullable();
                $table->bigInteger('type_id')->unsigned();
                $table->string('market_name');
                $table->string('location_name');
                $table->string('type_name');
                $table->string('previous_status', 20)->nullable();
                $table->string('current_status', 20);
                $table->integer('current_quantity')->unsigned()->default(0);
                $table->integer('warning_quantity')->unsigned()->default(0);
                $table->integer('desired_quantity')->unsigned()->default(0);
                $table->timestamps();

                $table->foreign('market_id')
                    ->references('id')
                    ->on('seat_market_seeding_markets')
                    ->onDelete('set null');

                $table->foreign('item_id')
                    ->references('id')
                    ->on('seat_market_seeding_items')
                    ->onDelete('set null');

                $table->index(['market_id', 'created_at'], 'sms_hist_market_created_idx');
                $table->index(['type_id', 'created_at'], 'sms_hist_type_created_idx');
                $table->index(['current_status', 'created_at'], 'sms_hist_status_created_idx');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('seat_market_seeding_stock_history');

        Schema::table('seat_market_seeding_markets', function (Blueprint $table) {
            $table->dropColumn([
                'last_refreshed_at',
                'last_refresh_status',
                'last_refresh_message',
                'last_refresh_orders',
            ]);
        });
    }
}
