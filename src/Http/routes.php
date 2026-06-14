<?php

Route::group([
    'namespace' => 'Raikia\SeatMarketSeeding\Http\Controllers',
    'prefix' => 'market-seeding',
    'middleware' => ['web', 'auth', 'can:seat-market-seeding.view'],
], function () {
    Route::get('/', [
        'as' => 'market-seeding.index',
        'uses' => 'MarketSeedingController@index',
    ]);

    Route::get('/markets/{market}/export', [
        'as' => 'market-seeding.export',
        'uses' => 'MarketSeedingController@export',
    ]);

    Route::group(['middleware' => 'can:seat-market-seeding.manager'], function () {
        Route::get('/settings', [
            'as' => 'market-seeding.settings',
            'uses' => 'SettingsController@index',
        ]);
        Route::post('/markets', [
            'as' => 'market-seeding.markets.store',
            'uses' => 'SettingsController@storeMarket',
        ]);
        Route::put('/markets/{market}', [
            'as' => 'market-seeding.markets.update',
            'uses' => 'SettingsController@updateMarket',
        ]);
        Route::delete('/markets/{market}', [
            'as' => 'market-seeding.markets.destroy',
            'uses' => 'SettingsController@destroyMarket',
        ]);
        Route::post('/profiles', [
            'as' => 'market-seeding.profiles.store',
            'uses' => 'SettingsController@storeProfile',
        ]);
        Route::put('/profiles/{profile}', [
            'as' => 'market-seeding.profiles.update',
            'uses' => 'SettingsController@updateProfile',
        ]);
        Route::delete('/profiles/{profile}', [
            'as' => 'market-seeding.profiles.destroy',
            'uses' => 'SettingsController@destroyProfile',
        ]);
        Route::post('/markets/refresh', [
            'as' => 'market-seeding.markets.refresh-all',
            'uses' => 'SettingsController@refreshMarkets',
        ]);
        Route::post('/markets/{market}/items', [
            'as' => 'market-seeding.items.store',
            'uses' => 'SettingsController@storeItem',
        ]);
        Route::post('/markets/{market}/items/import', [
            'as' => 'market-seeding.items.import',
            'uses' => 'SettingsController@importItems',
        ]);
        Route::post('/markets/{market}/items/import-saved-fitting', [
            'as' => 'market-seeding.items.import-saved-fitting',
            'uses' => 'SettingsController@importSavedFitting',
        ]);
        Route::put('/items/{item}', [
            'as' => 'market-seeding.items.update',
            'uses' => 'SettingsController@updateItem',
        ]);
        Route::delete('/items/{item}', [
            'as' => 'market-seeding.items.destroy',
            'uses' => 'SettingsController@destroyItem',
        ]);
        Route::get('/search/items', [
            'as' => 'market-seeding.search.items',
            'uses' => 'SettingsController@searchItems',
        ]);
        Route::get('/search/locations', [
            'as' => 'market-seeding.search.locations',
            'uses' => 'SettingsController@searchLocations',
        ]);
        Route::get('/search/saved-fittings', [
            'as' => 'market-seeding.search.saved-fittings',
            'uses' => 'SettingsController@searchSavedFittings',
        ]);
    });
});
