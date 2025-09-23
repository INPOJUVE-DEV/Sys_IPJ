<?php

namespace Database\Seeders;

use App\Models\VolSite;
use Illuminate\Database\Seeder;

class VolSiteSeeder extends Seeder
{
    public function run(): void
    {
        $sites = [
            [
                'name' => 'Capital',
                'state' => 'San Luis Potosi',
                'city' => 'San Luis Potosi',
                'address' => 'Por definir',
                'active' => true,
            ],
            [
                'name' => 'Cd. Valles',
                'state' => 'San Luis Potosi',
                'city' => 'Ciudad Valles',
                'address' => 'Por definir',
                'active' => true,
            ],
        ];

        foreach ($sites as $site) {
            VolSite::updateOrCreate(
                ['name' => $site['name']],
                $site
            );
        }
    }
}
