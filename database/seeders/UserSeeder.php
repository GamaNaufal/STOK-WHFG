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
        User::firstOrCreate(
            ['email' => 'admin@yamato.local'],
            [
                'name' => 'Admin Yamato',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );

        // Admin Warehouse User
        User::firstOrCreate(
            ['email' => 'admin.wh@warehouse.local'],
            [
                'name' => 'Admin Warehouse',
                'password' => Hash::make('password'),
                'role' => 'admin_warehouse',
            ]
        );

        // Warehouse Operator User
        User::firstOrCreate(
            ['email' => 'andi@warehouse.local'],
            [
                'name' => 'Andi Warehouse',
                'password' => Hash::make('password'),
                'role' => 'warehouse_operator',
            ]
        );

        // Sales User
        User::firstOrCreate(
            ['email' => 'siti@sales.local'],
            [
                'name' => 'Siti Sales',
                'password' => Hash::make('password'),
                'role' => 'sales',
            ]
        );

        // PPC User
        User::firstOrCreate(
            ['email' => 'pepe@ppc.local'],
            [
                'name' => 'Pepe PPC',
                'password' => Hash::make('password'),
                'role' => 'ppc',
            ]
        );

        // Supervisi User
        User::firstOrCreate(
            ['email' => 'spv@supervisi.local'],
            [
                'name' => 'SPV Yamato',
                'password' => Hash::make('password'),
                'role' => 'supervisi',
            ]
        );
    }
}
