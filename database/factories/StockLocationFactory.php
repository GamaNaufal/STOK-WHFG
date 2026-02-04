<?php

namespace Database\Factories;

use App\Models\Pallet;
use App\Models\StockLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockLocation>
 */
class StockLocationFactory extends Factory
{
    protected $model = StockLocation::class;

    public function definition(): array
    {
        return [
            'pallet_id' => PalletFactory::new(),
            'master_location_id' => null,
            'warehouse_location' => $this->faker->bothify('A-##-##'),
            'stored_at' => now(),
        ];
    }
}
