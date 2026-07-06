<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('seat_market_seeding_tracked_saved_fittings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('market_id');
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('fitting_id');
            $table->unsignedBigInteger('esi_fitting_id')->nullable();
            $table->string('fitting_name');
            $table->unsignedBigInteger('ship_type_id')->nullable();
            $table->string('ship_type_name')->nullable();
            $table->unsignedInteger('ship_multiplier')->default(5);
            $table->unsignedInteger('fitting_multiplier')->default(10);
            $table->unsignedInteger('warning_percentage')->default(33);
            $table->string('merge_mode', 16)->default('max');
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_status', 32)->nullable();
            $table->text('last_sync_message')->nullable();
            $table->timestamps();

            $table->foreign('market_id')
                ->references('id')
                ->on('seat_market_seeding_markets')
                ->onDelete('cascade');
            $table->unique(['market_id', 'character_id', 'fitting_id'], 'sms_tsf_market_character_fit_unique');
        });

        Schema::table('seat_market_seeding_item_sources', function (Blueprint $table) {
            $table->unsignedInteger('tracked_saved_fitting_id')->nullable()->after('tracked_doctrine_id');
            $table->foreign('tracked_saved_fitting_id', 'sms_sources_tracked_saved_fit_foreign')
                ->references('id')
                ->on('seat_market_seeding_tracked_saved_fittings')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('seat_market_seeding_item_sources', function (Blueprint $table) {
            $table->dropForeign('sms_sources_tracked_saved_fit_foreign');
            $table->dropColumn('tracked_saved_fitting_id');
        });

        Schema::dropIfExists('seat_market_seeding_tracked_saved_fittings');
    }
};
