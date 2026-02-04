<?php

namespace Database\Factories;

use App\Models\DeliveryPickItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeliveryPickItem>
 */
class DeliveryPickItemFactory extends Factory
{
    protected $model = DeliveryPickItem::class;

    public function definition(): array
    {
        return [
            'pick_session_id' => DeliveryPickSessionFactory::new(),
            'box_id' => BoxFactory::new(),
            'part_number' => $this->faker->bothify('PN-####'),
            'pcs_quantity' => 10,
            'status' => 'pending',
            'scanned_at' => null,
            'scanned_by' => null,
        ];
    }
}
