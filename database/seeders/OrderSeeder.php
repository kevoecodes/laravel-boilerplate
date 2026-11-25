<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::pluck('id');
        $products = Product::all(['id', 'price']);

        for ($i = 0; $i < 80; $i++) {
            $product = $products->random();

            Order::factory()->create([
                'user_id' => $users->random(),
                'product_id' => $product->id,
                'unit_price' => $product->price,
            ]);
        }
    }
}
