<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => ucwords(fake()->unique()->words(fake()->numberBetween(2, 3), true)),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####-??')),
            'description' => fake()->sentence(12),
            'category' => fake()->randomElement(['Electronics', 'Books', 'Clothing', 'Home', 'Sports']),
            'price' => fake()->randomFloat(2, 5, 1500),
            'stock' => fake()->numberBetween(0, 500),
            'status' => fake()->randomElement(['active', 'active', 'active', 'inactive']),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }
}
