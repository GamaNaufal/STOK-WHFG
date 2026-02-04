<?php

namespace Database\Factories;

use App\Models\Pallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pallet>
 */
class PalletFactory extends Factory
{
    protected $model = Pallet::class;

    public function definition(): array
    {
        return [
            'pallet_number' => 'PLT-' . $this->faker->unique()->numberBetween(1, 9999),
        ];
    }
}
