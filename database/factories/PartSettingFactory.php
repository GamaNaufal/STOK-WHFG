<?php

namespace Database\Factories;

use App\Models\PartSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PartSetting>
 */
class PartSettingFactory extends Factory
{
    protected $model = PartSetting::class;

    public function definition(): array
    {
        return [
            'part_number' => $this->faker->unique()->bothify('PN-####'),
            'qty_box' => 10,
        ];
    }
}
