<?php

namespace Raikia\SeatMarketSeeding\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Raikia\SeatMarketSeeding\Models\SeededMarket;
use Seat\Web\Models\User;

abstract class TestCase extends BaseTestCase
{
    use CreatesDatabaseSchema;

    public function createApplication()
    {
        $app = require dirname(__DIR__, 3) . '/bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('cache.default', 'array');
        Config::set('queue.default', 'sync');
        Cache::flush();

        $this->createDatabaseSchema();
        $this->seedUser();
    }

    protected function seedUser(array $overrides = []): User
    {
        $user = User::withoutEvents(fn () => User::query()->create(array_merge([
            'name' => 'admin',
            'admin' => true,
            'active' => true,
        ], $overrides)));

        auth()->guard()->setUser($user);

        return $user;
    }

    protected function createMarket(array $overrides = []): SeededMarket
    {
        return SeededMarket::create(array_merge([
            'name' => 'Delve Staging',
            'location_id' => 60000001,
            'location_name' => 'CX8-6K - Bloodstone',
            'region_id' => 10000060,
            'solar_system_id' => 30004759,
            'is_structure' => true,
        ], $overrides));
    }

    protected function seedType(int $typeId, string $typeName, array $overrides = []): void
    {
        DB::table('invTypes')->insert(array_merge([
            'typeID' => $typeId,
            'groupID' => $overrides['groupID'] ?? 53,
            'typeName' => $typeName,
            'description' => '',
            'mass' => 0,
            'volume' => $overrides['volume'] ?? 5,
            'capacity' => 0,
            'portionSize' => 1,
            'raceID' => null,
            'basePrice' => 0,
            'published' => true,
            'marketGroupID' => 1,
            'iconID' => null,
            'soundID' => null,
            'graphicID' => null,
        ], $overrides));
    }

    protected function seedSde(): void
    {
        DB::table('invCategories')->insert([
            ['categoryID' => 6, 'categoryName' => 'Ship', 'published' => true],
            ['categoryID' => 7, 'categoryName' => 'Module', 'published' => true],
            ['categoryID' => 8, 'categoryName' => 'Charge', 'published' => true],
        ]);

        DB::table('invGroups')->insert([
            ['groupID' => 25, 'categoryID' => 6, 'groupName' => 'Cruiser', 'published' => true],
            ['groupID' => 53, 'categoryID' => 7, 'groupName' => 'Propulsion Module', 'published' => true],
            ['groupID' => 83, 'categoryID' => 8, 'groupName' => 'Hybrid Charge', 'published' => true],
        ]);

    }
}
