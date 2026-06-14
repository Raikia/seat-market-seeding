<?php

namespace Raikia\SeatMarketSeeding;

use Raikia\SeatMarketSeeding\Console\Commands\RefreshMarketSeedingMarkets;
use Raikia\SeatMarketSeeding\Database\Seeders\ProfileSeeder;
use Raikia\SeatMarketSeeding\Database\Seeders\ScheduleSeeder;
use Seat\Services\AbstractSeatPlugin;

class MarketSeedingServiceProvider extends AbstractSeatPlugin
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->addRoutes();
        $this->addViews();
        $this->addTranslations();
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        $this->registerPermissions(__DIR__ . '/Config/market-seeding.permissions.php', 'seat-market-seeding');
        $this->registerCommands();
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/Config/market-seeding.sidebar.php', 'package.sidebar'
        );
        $this->registerDatabaseSeeders([
            ScheduleSeeder::class,
            ProfileSeeder::class,
        ]);
    }

    /**
     * Return the plugin name.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'SeAT Market Seeding';
    }

    /**
     * Return the package repository URL.
     *
     * @return string
     */
    public function getPackageRepositoryUrl(): string
    {
        return 'https://github.com/raikia/seat-market-seeding';
    }

    /**
     * Return the packagist package name.
     *
     * @return string
     */
    public function getPackagistPackageName(): string
    {
        return 'seat-market-seeding';
    }

    /**
     * Return the packagist vendor name.
     *
     * @return string
     */
    public function getPackagistVendorName(): string
    {
        return 'raikia';
    }

    private function addRoutes()
    {
        if (!$this->app->routesAreCached()) {
            include __DIR__ . '/Http/routes.php';
        }
    }

    private function addViews()
    {
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'seat-market-seeding');
    }

    private function addTranslations()
    {
        $this->loadTranslationsFrom(__DIR__ . '/resources/lang', 'seat-market-seeding');
    }

    private function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RefreshMarketSeedingMarkets::class,
            ]);
        }
    }

}
