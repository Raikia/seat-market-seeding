<?php

namespace Raikia\SeatMarketSeeding\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

trait CreatesDatabaseSchema
{
    protected function createDatabaseSchema(): void
    {
        $this->dropKnownTables();

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->unsignedBigInteger('main_character_id')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('admin')->default(false);
            $table->string('remember_token')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->text('logo')->nullable();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('user_id');
        });

        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->bigInteger('character_id')->primary();
            $table->unsignedInteger('user_id')->nullable();
            $table->integer('version')->default(2);
            $table->string('character_owner_hash')->nullable();
            $table->text('refresh_token')->nullable();
            $table->string('scopes_profile')->nullable();
            $table->text('scopes')->nullable();
            $table->timestamp('expires_on')->nullable();
            $table->text('token')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('global_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->index();
            $table->mediumText('value')->nullable();
            $table->timestamps();
        });

        Schema::create('seat_market_seeding_markets', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('sort_order')->default(0);
            $table->string('name');
            $table->bigInteger('location_id')->unsigned()->index();
            $table->string('location_name');
            $table->bigInteger('region_id')->unsigned()->default(10000002);
            $table->bigInteger('solar_system_id')->unsigned()->nullable();
            $table->boolean('is_structure')->default(false);
            $table->unsignedInteger('role_id')->nullable();
            $table->bigInteger('refresh_character_id')->unsigned()->nullable();
            $table->string('refresh_character_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_refreshed_at')->nullable();
            $table->string('last_refresh_status')->nullable();
            $table->text('last_refresh_message')->nullable();
            $table->unsignedInteger('last_refresh_orders')->nullable();
            $table->timestamps();
        });

        Schema::create('seat_market_seeding_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('market_id');
            $table->bigInteger('type_id')->unsigned();
            $table->string('type_name');
            $table->integer('desired_quantity')->unsigned();
            $table->integer('warning_quantity')->unsigned()->default(0);
            $table->string('stock_status')->default('unknown');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['market_id', 'type_id']);
        });

        Schema::create('seat_market_seeding_item_sources', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('market_id');
            $table->unsignedInteger('item_id')->nullable();
            $table->unsignedInteger('tracked_doctrine_id')->nullable();
            $table->unsignedInteger('tracked_saved_fitting_id')->nullable();
            $table->string('source_type');
            $table->string('source_key');
            $table->bigInteger('type_id')->unsigned();
            $table->string('type_name');
            $table->integer('quantity')->unsigned();
            $table->integer('warning_quantity')->unsigned()->nullable();
            $table->timestamps();
            $table->unique(['market_id', 'source_type', 'source_key', 'type_id'], 'sms_sources_unique');
        });

        Schema::create('seat_market_seeding_tracked_doctrines', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('market_id');
            $table->unsignedBigInteger('doctrine_id');
            $table->string('doctrine_name');
            $table->unsignedInteger('multiplier')->default(10);
            $table->unsignedInteger('warning_percentage')->default(33);
            $table->string('merge_mode')->default('max');
            $table->string('fit_aggregation_mode')->default('max');
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_status')->nullable();
            $table->text('last_sync_message')->nullable();
            $table->timestamps();
        });

        Schema::create('seat_market_seeding_tracked_doctrine_fits', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tracked_doctrine_id');
            $table->unsignedBigInteger('fitting_id');
            $table->string('fitting_name')->nullable();
            $table->unsignedBigInteger('ship_type_id')->nullable();
            $table->string('ship_type_name')->nullable();
            $table->unsignedInteger('ship_multiplier')->default(10);
            $table->unsignedInteger('fitting_multiplier')->default(10);
            $table->timestamps();
        });

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
            $table->string('merge_mode')->default('max');
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_status')->nullable();
            $table->text('last_sync_message')->nullable();
            $table->timestamps();
        });

        Schema::create('seat_market_seeding_profiles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->mediumText('stock_list');
            $table->timestamps();
        });

        Schema::create('seat_market_seeding_settings', function (Blueprint $table) {
            $table->string('setting')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('seat_market_seeding_stock_history', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('market_id')->nullable();
            $table->unsignedInteger('item_id')->nullable();
            $table->unsignedInteger('role_id')->nullable();
            $table->bigInteger('type_id')->unsigned();
            $table->string('market_name')->nullable();
            $table->string('location_name')->nullable();
            $table->string('type_name');
            $table->string('previous_status')->nullable();
            $table->string('current_status');
            $table->integer('current_quantity')->unsigned()->default(0);
            $table->integer('warning_quantity')->unsigned()->default(0);
            $table->integer('desired_quantity')->unsigned()->default(0);
            $table->timestamps();
        });

        Schema::create('seat_market_seeding_stock_snapshots', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('market_id')->nullable();
            $table->unsignedInteger('item_id')->nullable();
            $table->unsignedInteger('role_id')->nullable();
            $table->bigInteger('type_id')->unsigned();
            $table->string('market_name')->nullable();
            $table->string('location_name')->nullable();
            $table->string('type_name')->nullable();
            $table->string('type_category')->default('Unknown');
            $table->integer('previous_quantity')->unsigned()->nullable();
            $table->integer('current_quantity')->unsigned()->default(0);
            $table->integer('estimated_sold_quantity')->unsigned()->default(0);
            $table->integer('restocked_quantity')->unsigned()->default(0);
            $table->integer('desired_quantity')->unsigned()->default(0);
            $table->integer('warning_quantity')->unsigned()->default(0);
            $table->string('stock_status')->default('unknown');
            $table->timestamps();
        });

        Schema::create('seat_market_seeding_stock_daily_summaries', function (Blueprint $table) {
            $table->increments('id');
            $table->date('summary_date');
            $table->unsignedInteger('market_id')->nullable();
            $table->unsignedInteger('item_id')->nullable();
            $table->unsignedInteger('role_id')->nullable();
            $table->bigInteger('type_id')->unsigned();
            $table->string('market_name')->nullable();
            $table->string('location_name')->nullable();
            $table->string('type_name');
            $table->string('type_category')->nullable();
            $table->integer('estimated_sold_quantity')->unsigned()->default(0);
            $table->integer('restocked_quantity')->unsigned()->default(0);
            $table->integer('sales_events')->unsigned()->default(0);
            $table->integer('low_events')->unsigned()->default(0);
            $table->integer('empty_events')->unsigned()->default(0);
            $table->integer('stocked_events')->unsigned()->default(0);
            $table->integer('total_shortage')->unsigned()->default(0);
            $table->integer('latest_current_quantity')->unsigned()->default(0);
            $table->integer('latest_desired_quantity')->unsigned()->default(0);
            $table->integer('latest_warning_quantity')->unsigned()->default(0);
            $table->timestamp('last_sold_at')->nullable();
            $table->timestamp('last_needed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('seat_market_seeding_target_histories', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('market_id')->nullable();
            $table->unsignedInteger('item_id')->nullable();
            $table->bigInteger('type_id')->unsigned();
            $table->string('market_name')->nullable();
            $table->string('location_name')->nullable();
            $table->string('type_name');
            $table->integer('old_target_quantity')->unsigned()->nullable();
            $table->integer('new_target_quantity')->unsigned()->nullable();
            $table->integer('old_warning_quantity')->unsigned()->nullable();
            $table->integer('new_warning_quantity')->unsigned()->nullable();
            $table->string('change_type');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->timestamps();
        });

        Schema::create('invCategories', function (Blueprint $table) {
            $table->bigInteger('categoryID')->primary();
            $table->string('categoryName')->nullable();
            $table->boolean('published')->default(true);
        });

        Schema::create('invGroups', function (Blueprint $table) {
            $table->bigInteger('groupID')->primary();
            $table->bigInteger('categoryID')->nullable();
            $table->string('groupName')->nullable();
            $table->boolean('published')->default(true);
        });

        Schema::create('invTypes', function (Blueprint $table) {
            $table->bigInteger('typeID')->primary();
            $table->bigInteger('groupID')->nullable();
            $table->string('typeName')->nullable();
            $table->text('description')->nullable();
            $table->double('mass')->nullable();
            $table->double('volume')->nullable();
            $table->double('capacity')->nullable();
            $table->integer('portionSize')->nullable();
            $table->integer('raceID')->nullable();
            $table->double('basePrice')->nullable();
            $table->boolean('published')->default(true);
            $table->bigInteger('marketGroupID')->nullable();
            $table->integer('metaGroupID')->nullable();
            $table->integer('iconID')->nullable();
            $table->integer('soundID')->nullable();
            $table->integer('graphicID')->nullable();
        });

        Schema::create('dgmTypeAttributes', function (Blueprint $table) {
            $table->bigInteger('typeID');
            $table->integer('attributeID');
            $table->double('valueInt')->nullable();
            $table->double('valueFloat')->nullable();
            $table->primary(['typeID', 'attributeID']);
        });

        Schema::create('market_orders', function (Blueprint $table) {
            $table->bigIncrements('order_id');
            $table->bigInteger('location_id')->index();
            $table->bigInteger('type_id')->index();
            $table->integer('volume_remaining')->unsigned()->default(0);
            $table->integer('volume_total')->unsigned()->default(0);
            $table->double('price')->default(0);
            $table->boolean('is_buy_order')->nullable()->default(false);
            $table->timestamp('issued')->nullable();
            $table->timestamp('expiry')->nullable();
            $table->integer('duration')->nullable();
            $table->bigInteger('system_id')->nullable();
            $table->integer('min_volume')->nullable();
            $table->string('range')->nullable();
            $table->timestamps();
        });

        Schema::create('character_infos', function (Blueprint $table) {
            $table->bigInteger('character_id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('character_orders', function (Blueprint $table) {
            $table->bigInteger('character_id');
            $table->bigInteger('order_id');
            $table->integer('type_id');
            $table->integer('region_id')->default(10000002);
            $table->bigInteger('location_id');
            $table->string('range')->default('station');
            $table->boolean('is_buy_order')->nullable()->default(false);
            $table->double('price');
            $table->integer('volume_total');
            $table->integer('volume_remain');
            $table->dateTime('issued');
            $table->integer('min_volume')->nullable();
            $table->integer('duration');
            $table->boolean('is_corporation')->default(false);
            $table->double('escrow')->nullable();
            $table->string('state')->default('active');
            $table->timestamps();
            $table->primary(['character_id', 'order_id']);
        });

        Schema::create('market_prices', function (Blueprint $table) {
            $table->bigInteger('type_id')->primary();
            $table->double('average_price')->nullable();
            $table->double('sell_price')->nullable();
            $table->double('adjusted_price')->nullable();
            $table->timestamps();
        });
    }

    protected function dropKnownTables(): void
    {
        foreach ([
            'market_prices',
            'character_orders',
            'character_infos',
            'market_orders',
            'invTypes',
            'invGroups',
            'invCategories',
            'seat_market_seeding_target_histories',
            'seat_market_seeding_stock_daily_summaries',
            'seat_market_seeding_stock_snapshots',
            'seat_market_seeding_stock_history',
            'seat_market_seeding_settings',
            'seat_market_seeding_profiles',
            'seat_market_seeding_tracked_doctrine_fits',
            'seat_market_seeding_tracked_saved_fittings',
            'seat_market_seeding_tracked_doctrines',
            'seat_market_seeding_item_sources',
            'seat_market_seeding_items',
            'seat_market_seeding_markets',
            'global_settings',
            'refresh_tokens',
            'role_user',
            'roles',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
}
