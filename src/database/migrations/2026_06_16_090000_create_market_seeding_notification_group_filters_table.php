<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMarketSeedingNotificationGroupFiltersTable extends Migration
{
    public function up()
    {
        Schema::create('seat_market_seeding_notification_group_filters', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('notification_group_id');
            $table->json('allowed_market_ids')->nullable();
            $table->timestamps();

            $table->unique('notification_group_id', 'ms_ngf_group_unique');

            $table->foreign('notification_group_id', 'ms_ngf_group_fk')
                ->references('id')
                ->on('notification_groups')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('seat_market_seeding_notification_group_filters');
    }
}
