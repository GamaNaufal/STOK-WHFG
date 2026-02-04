<?php

namespace Database\Factories;

use App\Models\MasterLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MasterLocation>
 */
class MasterLocationFactory extends Factory
{
    protected $model = MasterLocation::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->bothify('A-##-##'),
            'is_occupied' => false,
            'current_pallet_id' => null,
        ];
    }
}
