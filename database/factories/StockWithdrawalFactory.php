<?php

namespace Database\Factories;

use App\Models\StockWithdrawal;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockWithdrawal>
 */
class StockWithdrawalFactory extends Factory
{
    protected $model = StockWithdrawal::class;

    public function definition(): array
    {
        return [
            'withdrawal_batch_id' => (string) Str::uuid(),
            'user_id' => UserFactory::new(),
            'pallet_item_id' => null,
            'box_id' => null,
            'part_number' => $this->faker->bothify('PN-####'),
            'pcs_quantity' => 10,
            'box_quantity' => 1,
            'warehouse_location' => $this->faker->bothify('A-##-##'),
            'status' => 'completed',
            'notes' => null,
            'withdrawn_at' => now(),
        ];
    }
}
