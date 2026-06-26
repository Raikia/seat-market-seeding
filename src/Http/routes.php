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

    Route::get('/history', [
        'as' => 'market-seeding.history',
        'uses' => 'MarketSeedingController@history',
    ]);

    Route::get('/history/transitions', [
        'as' => 'market-seeding.history.transitions',
        'uses' => 'MarketSeedingController@historyTransitions',
    ]);

    Route::get('/items/{item}/history', [
        'as' => 'market-seeding.items.history',
        'uses' => 'MarketSeedingController@itemHistory',
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
        Route::post('/settings/general', [
            'as' => 'market-seeding.settings.general',
            'uses' => 'SettingsController@updateGeneralSettings',
        ]);
        Route::delete('/settings/history', [
            'as' => 'market-seeding.settings.history.clear',
            'uses' => 'SettingsController@clearHistory',
        ]);
        Route::delete('/settings/audit-history', [
            'as' => 'market-seeding.settings.audit-history.clear',
            'uses' => 'SettingsController@clearAuditHistory',
        ]);
        Route::post('/markets', [
            'as' => 'market-seeding.markets.store',
            'uses' => 'SettingsController@storeMarket',
        ]);
        Route::put('/markets/{market}', [
            'as' => 'market-seeding.markets.update',
            'uses' => 'SettingsController@updateMarket',
        ]);
        Route::post('/markets/{market}/move', [
            'as' => 'market-seeding.markets.move',
            'uses' => 'SettingsController@moveMarket',
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
        Route::post('/history/recommendations/apply', [
            'as' => 'market-seeding.history.recommendations.apply',
            'uses' => 'MarketSeedingController@applyHistoryRecommendations',
        ]);
        Route::post('/markets/{market}/items', [
            'as' => 'market-seeding.items.store',
            'uses' => 'SettingsController@storeItem',
        ]);
        Route::delete('/markets/{market}/items', [
            'as' => 'market-seeding.items.clear-market',
            'uses' => 'SettingsController@clearMarketItems',
        ]);
        Route::post('/markets/{market}/items/import', [
            'as' => 'market-seeding.items.import',
            'uses' => 'SettingsController@importItems',
        ]);
        Route::post('/markets/{market}/items/preview', [
            'as' => 'market-seeding.items.preview',
            'uses' => 'SettingsController@previewItems',
        ]);
        Route::post('/markets/{market}/items/import-saved-fitting', [
            'as' => 'market-seeding.items.import-saved-fitting',
            'uses' => 'SettingsController@importSavedFitting',
        ]);
        Route::post('/markets/{market}/items/preview-saved-fitting', [
            'as' => 'market-seeding.items.preview-saved-fitting',
            'uses' => 'SettingsController@previewSavedFitting',
        ]);
        Route::post('/markets/{market}/tracked-doctrines', [
            'as' => 'market-seeding.tracked-doctrines.store',
            'uses' => 'SettingsController@storeTrackedDoctrine',
        ]);
        Route::post('/markets/{market}/tracked-doctrines/preview', [
            'as' => 'market-seeding.tracked-doctrines.preview',
            'uses' => 'SettingsController@previewTrackedDoctrine',
        ]);
        Route::put('/tracked-doctrines/{trackedDoctrine}', [
            'as' => 'market-seeding.tracked-doctrines.update',
            'uses' => 'SettingsController@updateTrackedDoctrine',
        ]);
        Route::delete('/tracked-doctrines/{trackedDoctrine}', [
            'as' => 'market-seeding.tracked-doctrines.destroy',
            'uses' => 'SettingsController@destroyTrackedDoctrine',
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
        Route::get('/search/doctrines', [
            'as' => 'market-seeding.search.doctrines',
            'uses' => 'SettingsController@searchDoctrines',
        ]);
    });
});
