<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('seat_market_seeding_stock_snapshots')) {
            return;
        }

        Schema::create('seat_market_seeding_stock_snapshots', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('market_id')->nullable();
            $table->unsignedInteger('item_id')->nullable();
            $table->unsignedInteger('role_id')->nullable();
            $table->bigInteger('type_id')->unsigned();
            $table->string('market_name');
            $table->string('location_name');
            $table->string('type_name');
            $table->string('type_category')->default('Unknown');
            $table->integer('previous_quantity')->unsigned()->nullable();
            $table->integer('current_quantity')->unsigned()->default(0);
            $table->integer('estimated_sold_quantity')->unsigned()->default(0);
            $table->integer('restocked_quantity')->unsigned()->default(0);
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

            $table->index(['market_id', 'created_at'], 'sms_snap_market_created_idx');
            $table->index(['type_id', 'created_at'], 'sms_snap_type_created_idx');
            $table->index(['type_category', 'created_at'], 'sms_snap_category_created_idx');
            $table->index(['estimated_sold_quantity', 'created_at'], 'sms_snap_sold_created_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('seat_market_seeding_stock_snapshots');
    }
};
