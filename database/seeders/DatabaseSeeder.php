<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            StockSeeder::class,
        ]);

        // Optional: heavy Excel import (disable by default for faster refresh)
        if (env('SEED_BOX_DATA', false)) {
            $this->call([
                BoxDataSeeder::class,
            ]);
        }
    }
}
