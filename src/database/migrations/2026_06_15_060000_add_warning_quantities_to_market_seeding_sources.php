<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('seat_market_seeding_tracked_doctrines', function (Blueprint $table) {
            $table->unsignedTinyInteger('warning_percentage')->default(33)->after('multiplier');
        });

        Schema::table('seat_market_seeding_item_sources', function (Blueprint $table) {
            $table->unsignedInteger('warning_quantity')->nullable()->after('quantity');
        });

        DB::table('seat_market_seeding_item_sources')
            ->join('seat_market_seeding_items', 'seat_market_seeding_item_sources.item_id', '=', 'seat_market_seeding_items.id')
            ->where('seat_market_seeding_item_sources.source_type', 'manual')
            ->update([
                'seat_market_seeding_item_sources.warning_quantity' => DB::raw('seat_market_seeding_items.warning_quantity'),
            ]);
    }

    public function down()
    {
        Schema::table('seat_market_seeding_item_sources', function (Blueprint $table) {
            $table->dropColumn('warning_quantity');
        });

        Schema::table('seat_market_seeding_tracked_doctrines', function (Blueprint $table) {
            $table->dropColumn('warning_percentage');
        });
    }
};
