<?php

namespace Database\Factories;

use App\Models\PalletItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PalletItem>
 */
class PalletItemFactory extends Factory
{
    protected $model = PalletItem::class;

    public function definition(): array
    {
        return [
            'pallet_id' => PalletFactory::new(),
            'part_number' => $this->faker->bothify('PN-####'),
            'box_quantity' => 1,
            'pcs_quantity' => 10,
        ];
    }
}
