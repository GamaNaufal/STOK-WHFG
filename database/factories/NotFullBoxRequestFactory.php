<?php

namespace Database\Factories;

use App\Models\NotFullBoxRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NotFullBoxRequest>
 */
class NotFullBoxRequestFactory extends Factory
{
    protected $model = NotFullBoxRequest::class;

    public function definition(): array
    {
        return [
            'box_number' => $this->faker->unique()->bothify('BOX-NF-####'),
            'part_number' => $this->faker->bothify('PN-####'),
            'pcs_quantity' => 5,
            'fixed_qty' => 10,
            'reason' => 'Dummy reason',
            'request_type' => 'supplement',
            'delivery_order_id' => DeliveryOrderFactory::new(),
            'requested_by' => UserFactory::new(),
            'target_pallet_id' => null,
            'target_location_id' => MasterLocationFactory::new(),
            'target_location_code' => null,
            'status' => 'pending',
            'approved_by' => null,
            'approved_at' => null,
            'box_id' => null,
        ];
    }
}
