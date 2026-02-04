<?php

namespace Database\Factories;

use App\Models\DeliveryOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeliveryOrderItem>
 */
class DeliveryOrderItemFactory extends Factory
{
    protected $model = DeliveryOrderItem::class;

    public function definition(): array
    {
        return [
            'delivery_order_id' => DeliveryOrderFactory::new(),
            'part_number' => $this->faker->bothify('PN-####'),
            'quantity' => 10,
            'fulfilled_quantity' => 0,
        ];
    }
}
