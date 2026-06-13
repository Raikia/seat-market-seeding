<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMarketSeedingTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('seat_market_seeding_markets', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->bigInteger('location_id')->index();
            $table->string('location_name');
            $table->bigInteger('region_id')->unsigned()->default(10000002);
            $table->bigInteger('solar_system_id')->unsigned()->nullable();
            $table->boolean('is_structure')->default(false);
            $table->unsignedInteger('role_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('seat_market_seeding_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('market_id');
            $table->bigInteger('type_id')->unsigned();
            $table->string('type_name');
            $table->integer('desired_quantity')->unsigned();
            $table->integer('warning_quantity')->unsigned()->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('market_id')
                ->references('id')
                ->on('seat_market_seeding_markets')
                ->onDelete('cascade');

            $table->unique(['market_id', 'type_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('seat_market_seeding_items');
        Schema::dropIfExists('seat_market_seeding_markets');
    }
}
