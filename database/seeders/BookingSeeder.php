<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Schedule;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schedules = Schedule::all();

        foreach ($schedules as $schedule) {
            Booking::factory()
                ->count(2)
                ->for($schedule)
                ->create();
        }
    }
}
