<?php

namespace Database\Seeders;

use App\Models\Schedule;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = Service::all();

        foreach ($services as $service) {
            Schedule::factory()
                ->count(2)
                ->for($service)
                ->create();

            Schedule::factory()
                ->specificDate()
                ->for($service)
                ->create();
        }
    }
}
