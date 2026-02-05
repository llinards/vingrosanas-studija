<?php

namespace Database\Seeders;

use App\Models\Coach;
use App\Models\User;
use Illuminate\Database\Seeder;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Linards Lazdiņš',
            'email' => 'linards@slmedia.lv',
        ]);

        Coach::factory()->count(4)->create();

        $this->call([
            ServiceTypeSeeder::class,
            ServiceSeeder::class,
        ]);
    }
}
