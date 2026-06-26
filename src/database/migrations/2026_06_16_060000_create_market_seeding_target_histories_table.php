<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('seat_market_seeding_target_histories', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('market_id')->nullable()->index();
            $table->unsignedInteger('item_id')->nullable()->index();
            $table->bigInteger('type_id')->unsigned()->index();
            $table->string('market_name')->nullable();
            $table->string('location_name')->nullable();
            $table->string('type_name');
            $table->integer('old_target_quantity')->unsigned()->nullable();
            $table->integer('new_target_quantity')->unsigned()->nullable();
            $table->integer('old_warning_quantity')->unsigned()->nullable();
            $table->integer('new_warning_quantity')->unsigned()->nullable();
            $table->string('change_type')->index();
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->string('user_name')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'created_at'], 'sms_target_hist_item_created_idx');
            $table->index(['market_id', 'type_id', 'created_at'], 'sms_target_hist_market_type_created_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('seat_market_seeding_target_histories');
    }
};
