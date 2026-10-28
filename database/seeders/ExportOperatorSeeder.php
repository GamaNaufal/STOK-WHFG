<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ExportOperatorSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'export@warehouse.local'],
            [
                'name' => 'Operator Export',
                'password' => Hash::make('password'),
                'role' => 'export_operator',
                'is_active' => true,
            ]
        );
    }
}
