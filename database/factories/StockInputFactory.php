<?php

namespace Database\Factories;

use App\Models\StockInput;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockInput>
 */
class StockInputFactory extends Factory
{
    protected $model = StockInput::class;

    public function definition(): array
    {
        return [
            'pallet_id' => PalletFactory::new(),
            'pallet_item_id' => null,
            'user_id' => UserFactory::new(),
            'warehouse_location' => $this->faker->bothify('A-##-##'),
            'pcs_quantity' => 10,
            'box_quantity' => 1,
            'stored_at' => now(),
            'part_numbers' => [$this->faker->bothify('PN-####')],
        ];
    }
}
