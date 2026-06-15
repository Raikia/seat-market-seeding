<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('seat_market_seeding_tracked_doctrines', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('market_id');
            $table->unsignedInteger('doctrine_id');
            $table->string('doctrine_name');
            $table->unsignedInteger('multiplier')->default(1);
            $table->string('merge_mode', 16)->default('max');
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_status', 32)->nullable();
            $table->text('last_sync_message')->nullable();
            $table->timestamps();

            $table->foreign('market_id')
                ->references('id')
                ->on('seat_market_seeding_markets')
                ->onDelete('cascade');
            $table->unique(['market_id', 'doctrine_id'], 'sms_td_market_doctrine_unique');
        });

        Schema::create('seat_market_seeding_item_sources', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('market_id');
            $table->unsignedInteger('item_id')->nullable();
            $table->unsignedInteger('tracked_doctrine_id')->nullable();
            $table->string('source_type', 32);
            $table->string('source_key', 128);
            $table->unsignedInteger('type_id');
            $table->string('type_name');
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->foreign('market_id')
                ->references('id')
                ->on('seat_market_seeding_markets')
                ->onDelete('cascade');
            $table->foreign('item_id')
                ->references('id')
                ->on('seat_market_seeding_items')
                ->onDelete('set null');
            $table->foreign('tracked_doctrine_id')
                ->references('id')
                ->on('seat_market_seeding_tracked_doctrines')
                ->onDelete('cascade');
            $table->unique(['market_id', 'source_type', 'source_key', 'type_id'], 'sms_sources_unique');
            $table->index(['market_id', 'type_id'], 'sms_sources_market_type');
        });

        DB::table('seat_market_seeding_items')
            ->orderBy('id')
            ->get()
            ->each(function ($item) {
                DB::table('seat_market_seeding_item_sources')->insert([
                    'market_id' => $item->market_id,
                    'item_id' => $item->id,
                    'tracked_doctrine_id' => null,
                    'source_type' => 'manual',
                    'source_key' => 'manual',
                    'type_id' => $item->type_id,
                    'type_name' => $item->type_name,
                    'quantity' => $item->desired_quantity,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down()
    {
        Schema::dropIfExists('seat_market_seeding_item_sources');
        Schema::dropIfExists('seat_market_seeding_tracked_doctrines');
    }
};
