<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Gateway>
 */
class GatewayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'      => fake()->randomElement(['gateway1', 'gateway2']),
            'is_active' => true,
            'priority'  => 1,
        ];
    }
}
