<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('seat_market_seeding_tracked_doctrines', 'fit_aggregation_mode')) {
            DB::statement("ALTER TABLE `seat_market_seeding_tracked_doctrines` MODIFY `fit_aggregation_mode` varchar(16) NOT NULL DEFAULT 'max'");
        }
    }

    public function down()
    {
        if (Schema::hasColumn('seat_market_seeding_tracked_doctrines', 'fit_aggregation_mode')) {
            DB::statement("ALTER TABLE `seat_market_seeding_tracked_doctrines` MODIFY `fit_aggregation_mode` varchar(16) NOT NULL DEFAULT 'sum'");
        }
    }
};
