<?php

namespace Database\Factories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'service',
            'name' => fake()->words(3, true),
            'unit' => 'ساعة',
            'price_minor' => fake()->numberBetween(1000, 500000),
            'currency' => 'SAR',
        ];
    }
}
