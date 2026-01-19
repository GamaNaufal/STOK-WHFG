<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin User
        User::create([
            'name' => 'Admin Yamato',
            'email' => 'admin@yamato.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        // Packing Department User
        User::create([
            'name' => 'Budi Packing',
            'email' => 'budi@packing.local',
            'password' => Hash::make('password'),
            'role' => 'packing_department',
        ]);

        // Warehouse Operator User
        User::create([
            'name' => 'Andi Warehouse',
            'email' => 'andi@warehouse.local',
            'password' => Hash::make('password'),
            'role' => 'warehouse_operator',
        ]);
    }
}
