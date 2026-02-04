<?php

namespace Database\Factories;

use App\Models\Box;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Box>
 */
class BoxFactory extends Factory
{
    protected $model = Box::class;

    public function definition(): array
    {
        $partNumber = $this->faker->bothify('PN-####');
        $pcsQty = 10;
        $boxNumber = $this->faker->unique()->bothify('BOX-####');

        return [
            'box_number' => $boxNumber,
            'part_number' => $partNumber,
            'part_name' => null,
            'pcs_quantity' => $pcsQty,
            'qty_box' => $pcsQty,
            'type_box' => null,
            'wk_transfer' => null,
            'lot01' => null,
            'lot02' => null,
            'lot03' => null,
            'qr_code' => $boxNumber . '|' . $partNumber . '|' . $pcsQty,
            'user_id' => UserFactory::new(),
            'is_withdrawn' => false,
            'withdrawn_at' => null,
            'is_not_full' => false,
            'not_full_reason' => null,
            'assigned_delivery_order_id' => null,
            'expired_status' => 'active',
            'handled_at' => null,
            'handled_by' => null,
        ];
    }
}
