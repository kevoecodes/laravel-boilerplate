<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 10),
            'unit_price' => fake()->randomFloat(2, 5, 1500),
            'status' => fake()->randomElement(Order::STATUSES),
            'notes' => fake()->optional()->sentence(),
            'created_at' => fake()->dateTimeBetween('-6 months'),
        ];
    }
}
