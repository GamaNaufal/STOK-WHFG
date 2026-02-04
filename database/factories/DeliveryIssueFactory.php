<?php

namespace Database\Factories;

use App\Models\DeliveryIssue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeliveryIssue>
 */
class DeliveryIssueFactory extends Factory
{
    protected $model = DeliveryIssue::class;

    public function definition(): array
    {
        return [
            'pick_session_id' => DeliveryPickSessionFactory::new(),
            'box_id' => null,
            'scanned_code' => $this->faker->bothify('BOX-####'),
            'issue_type' => 'scan_mismatch',
            'status' => 'pending',
            'resolved_by' => null,
            'resolved_at' => null,
            'notes' => null,
        ];
    }
}
