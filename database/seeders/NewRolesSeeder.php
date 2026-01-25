<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NewRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Sales User
        User::firstOrCreate(
            ['email' => 'siti@sales.local'],
            [
                'name' => 'Siti Sales',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'role' => 'sales',
            ]
        );

        // PPC User
        User::firstOrCreate(
            ['email' => 'pepe@ppc.local'],
            [
                'name' => 'Pepe PPC',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'role' => 'ppc',
            ]
        );
    }
}
