<?php

namespace Database\Factories;

use App\Models\DeliveryOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeliveryOrder>
 */
class DeliveryOrderFactory extends Factory
{
    protected $model = DeliveryOrder::class;

    public function definition(): array
    {
        return [
            'sales_user_id' => UserFactory::new(),
            'customer_name' => $this->faker->company(),
            'delivery_date' => now()->addDay()->toDateString(),
            'status' => 'pending',
            'notes' => null,
        ];
    }
}
