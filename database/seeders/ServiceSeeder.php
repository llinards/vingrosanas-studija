<?php

namespace Database\Seeders;

use App\Models\Coach;
use App\Models\Service;
use App\Models\ServiceType;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $serviceTypes = ServiceType::all();
        $coaches = Coach::all();

        foreach ($coaches as $coach) {
            Service::factory()
                ->count(2)
                ->for($coach)
                ->for($serviceTypes->random())
                ->create();
        }
    }
}
