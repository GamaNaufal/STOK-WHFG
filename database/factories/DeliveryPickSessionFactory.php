<?php

namespace Database\Factories;

use App\Models\DeliveryPickSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeliveryPickSession>
 */
class DeliveryPickSessionFactory extends Factory
{
    protected $model = DeliveryPickSession::class;

    public function definition(): array
    {
        return [
            'delivery_order_id' => DeliveryOrderFactory::new(),
            'created_by' => UserFactory::new(),
            'status' => 'scanning',
            'started_at' => now(),
            'approved_by' => null,
            'approved_at' => null,
            'approval_notes' => null,
            'completed_at' => null,
            'redo_until' => null,
            'completion_status' => 'pending',
        ];
    }
}
