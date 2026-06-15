<?php

namespace Raikia\SeatMarketSeeding\Database\Seeders;

use Illuminate\Database\Seeder;
use Raikia\SeatMarketSeeding\Models\MarketSeedingProfile;

class ProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach ($this->profiles() as $profile) {
            MarketSeedingProfile::firstOrCreate(
                ['name' => $profile['name']],
                [
                    'description' => $profile['description'],
                    'stock_list' => $this->stockList($profile['items']),
                ]
            );
        }
    }

    private function profiles(): array
    {
        return [
            [
                'name' => 'All common ammo, scripts, and charges',
                'description' => 'Broad starter profile for common T2/faction turret ammo, missiles, scripts, and navy cap boosters.',
                'items' => array_merge(
                    $this->turretAmmo(),
                    $this->missileAmmo(),
                    $this->scripts(),
                    $this->navyCapBoosters()
                ),
            ],
            [
                'name' => 'Common missile ammo',
                'description' => 'T2 and Caldari Navy rockets, light missiles, heavy missiles, HAMs, cruise missiles, and torpedoes.',
                'items' => $this->missileAmmo(),
            ],
            [
                'name' => 'Common turret ammo',
                'description' => 'T2 and faction hybrid, projectile, and laser ammo in small, medium, and large sizes.',
                'items' => $this->turretAmmo(),
            ],
            [
                'name' => 'Navy cap boosters',
                'description' => 'Common Navy Cap Booster sizes for frigates through capitals.',
                'items' => $this->navyCapBoosters(),
            ],
            [
                'name' => 'Common drones',
                'description' => 'Frequently stocked T2 combat drones, ECM drones, and utility drones.',
                'items' => [
                    ['Acolyte II', 50],
                    ['Infiltrator II', 40],
                    ['Praetor II', 20],
                    ['Hobgoblin II', 50],
                    ['Hammerhead II', 40],
                    ['Ogre II', 20],
                    ['Hornet II', 50],
                    ['Vespa II', 40],
                    ['Wasp II', 20],
                    ['Warrior II', 50],
                    ['Valkyrie II', 40],
                    ['Berserker II', 20],
                    ['Hornet EC-300', 50],
                    ['Vespa EC-600', 40],
                    ['Wasp EC-900', 20],
                    ['Light Armor Maintenance Bot I', 20],
                    ['Medium Armor Maintenance Bot I', 20],
                    ['Heavy Armor Maintenance Bot I', 10],
                    ['Light Shield Maintenance Bot I', 20],
                    ['Medium Shield Maintenance Bot I', 20],
                    ['Heavy Shield Maintenance Bot I', 10],
                    ['Light Armor Maintenance Bot II', 20],
                    ['Medium Armor Maintenance Bot II', 20],
                    ['Heavy Armor Maintenance Bot II', 10],
                    ['Light Shield Maintenance Bot II', 20],
                    ['Medium Shield Maintenance Bot II', 20],
                    ['Heavy Shield Maintenance Bot II', 10],
                    ['Salvage Drone I', 20],
                ],
            ],
            [
                'name' => 'Deployables, probes, and repair paste',
                'description' => 'Common quality-of-life consumables for staging markets.',
                'items' => [
                    ['Mobile Depot', 10],
                    ['Mobile Tractor Unit', 10],
                    ['Mobile Small Warp Disruptor I', 10],
                    ['Mobile Medium Warp Disruptor I', 10],
                    ['Mobile Large Warp Disruptor I', 5],
                    ['Nanite Repair Paste', 5000],
                    ['Sisters Core Scanner Probe', 200],
                    ['Sisters Combat Scanner Probe', 200],
                    ['Warp Disrupt Probe', 200],
                    ['Interdiction Sphere Launcher I', 10],
                    ['Cynosural Field Generator I', 10],
                    ['Industrial Cynosural Field Generator', 10],
                    ['Liquid Ozone', 50000],
                ],
            ],
        ];
    }

    private function turretAmmo(): array
    {
        $items = [];

        foreach (['S' => 5000, 'M' => 5000, 'L' => 3000] as $size => $quantity) {
            foreach (['Barrage', 'Hail', 'Quake', 'Tremor'] as $charge) {
                $items[] = [$charge . ' ' . $size, $quantity];
            }

            foreach (['Republic Fleet EMP', 'Republic Fleet Fusion', 'Republic Fleet Phased Plasma'] as $charge) {
                $items[] = [$charge . ' ' . $size, $quantity];
            }

            foreach (['Null', 'Void', 'Spike', 'Javelin'] as $charge) {
                $items[] = [$charge . ' ' . $size, $quantity];
            }

            foreach (['Federation Navy Antimatter Charge', 'Caldari Navy Antimatter Charge'] as $charge) {
                $items[] = [$charge . ' ' . $size, $quantity];
            }

            foreach (['Scorch', 'Conflagration', 'Aurora', 'Gleam'] as $charge) {
                $items[] = [$charge . ' ' . $size, 20];
            }

            foreach (['Imperial Navy Multifrequency', 'Imperial Navy Standard'] as $charge) {
                $items[] = [$charge . ' ' . $size, 20];
            }
        }

        return $items;
    }

    private function missileAmmo(): array
    {
        $items = [];

        foreach (['Inferno', 'Mjolnir', 'Nova', 'Scourge'] as $damage) {
            foreach ([
                'Rage Rocket' => 5000,
                'Javelin Rocket' => 5000,
                'Fury Light Missile' => 5000,
                'Precision Light Missile' => 5000,
                'Fury Heavy Missile' => 5000,
                'Precision Heavy Missile' => 5000,
                'Rage Heavy Assault Missile' => 5000,
                'Javelin Heavy Assault Missile' => 5000,
                'Fury Cruise Missile' => 3000,
                'Precision Cruise Missile' => 3000,
                'Rage Torpedo' => 3000,
                'Javelin Torpedo' => 3000,
            ] as $charge => $quantity) {
                $items[] = [$damage . ' ' . $charge, $quantity];
            }

            foreach ([
                'Caldari Navy Rocket' => 5000,
                'Caldari Navy Light Missile' => 5000,
                'Caldari Navy Heavy Missile' => 5000,
                'Caldari Navy Heavy Assault Missile' => 5000,
                'Caldari Navy Cruise Missile' => 3000,
                'Caldari Navy Torpedo' => 3000,
            ] as $charge => $quantity) {
                $items[] = ['Caldari Navy ' . $damage . ' ' . str_replace('Caldari Navy ', '', $charge), $quantity];
            }
        }

        return $items;
    }

    private function scripts(): array
    {
        return [
            ['Armor EM Resistance Script', 10],
            ['Armor Explosive Resistance Script', 10],
            ['Armor Kinetic Resistance Script', 10],
            ['Armor Thermal Resistance Script', 10],
            ['ECCM Script', 10],
            ['Focused Warp Disruption Script', 10],
            ['Focused Warp Scrambling Script', 10],
            ['Missile Precision Disruption Script', 10],
            ['Missile Precision Script', 10],
            ['Missile Range Disruption Script', 10],
            ['Missile Range Script', 10],
            ['Optimal Range Disruption Script', 10],
            ['Optimal Range Script', 10],
            ['Scan Resolution Dampening Script', 10],
            ['Scan Resolution Script', 10],
            ['Shield EM Resistance Script', 10],
            ['Shield Explosive Resistance Script', 10],
            ['Shield Kinetic Resistance Script', 10],
            ['Shield Thermal Resistance Script', 10],
            ['Targeting Range Dampening Script', 10],
            ['Targeting Range Script', 10],
            ['Tracking Speed Disruption Script', 10],
            ['Tracking Speed Script', 10],
        ];
    }

    private function navyCapBoosters(): array
    {
        return [
            ['Navy Cap Booster 25', 200],
            ['Navy Cap Booster 50', 200],
            ['Navy Cap Booster 75', 200],
            ['Navy Cap Booster 100', 200],
            ['Navy Cap Booster 150', 200],
            ['Navy Cap Booster 200', 200],
            ['Navy Cap Booster 400', 200],
            ['Navy Cap Booster 800', 200],
            ['Navy Cap Booster 3200', 100],
        ];
    }

    private function stockList(array $items): string
    {
        return collect($items)
            ->map(function (array $item) {
                return $item[0] . ' ' . $item[1];
            })
            ->implode("\n");
    }
}
