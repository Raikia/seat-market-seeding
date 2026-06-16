<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('seat_market_seeding_tracked_doctrines', function (Blueprint $table) {
            $table->string('fit_aggregation_mode', 16)
                ->default('max')
                ->after('merge_mode');
        });

        Schema::create('seat_market_seeding_tracked_doctrine_fits', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tracked_doctrine_id');
            $table->unsignedInteger('fitting_id');
            $table->string('fitting_name');
            $table->unsignedInteger('ship_type_id')->nullable();
            $table->string('ship_type_name')->nullable();
            $table->unsignedInteger('ship_multiplier')->default(1);
            $table->unsignedInteger('fitting_multiplier')->default(1);
            $table->timestamps();

            $table->foreign('tracked_doctrine_id', 'sms_tdf_tracked_doctrine_foreign')
                ->references('id')
                ->on('seat_market_seeding_tracked_doctrines')
                ->onDelete('cascade');
            $table->unique(['tracked_doctrine_id', 'fitting_id'], 'sms_tdf_tracked_fit_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('seat_market_seeding_tracked_doctrine_fits');

        Schema::table('seat_market_seeding_tracked_doctrines', function (Blueprint $table) {
            $table->dropColumn('fit_aggregation_mode');
        });
    }
};
