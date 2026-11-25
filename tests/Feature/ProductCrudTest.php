<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->admin()->create());
    }

    public function test_guests_cannot_access_products(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/products')->assertUnauthorized();
    }

    public function test_index_returns_paginated_envelope(): void
    {
        Product::factory()->count(20)->create();

        $this->getJson('/api/v1/products?per_page=5&page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'name', 'sku', 'price', 'stock', 'status']],
                'meta' => ['current_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 20);
    }

    public function test_index_supports_search_sort_and_filters(): void
    {
        Product::factory()->create(['name' => 'Blue Widget', 'price' => 30, 'status' => 'active']);
        Product::factory()->create(['name' => 'Red Widget', 'price' => 10, 'status' => 'active']);
        Product::factory()->create(['name' => 'Green Widget', 'price' => 20, 'status' => 'inactive']);
        Product::factory()->create(['name' => 'Gadget', 'price' => 99, 'status' => 'active']);

        $this->getJson('/api/v1/products?search=widget&sort=-price')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.name', 'Blue Widget')
            ->assertJsonPath('data.2.name', 'Red Widget');

        $this->getJson('/api/v1/products?search=WIDGET&filter[status]=active&sort=price')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.name', 'Red Widget');

        $this->getJson('/api/v1/products?filter[min_price]=15&filter[max_price]=50')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_index_ignores_unknown_sort_columns(): void
    {
        Product::factory()->count(3)->create();

        $this->getJson('/api/v1/products?sort=password')->assertOk()->assertJsonPath('meta.total', 3);
    }

    public function test_can_create_product(): void
    {
        $payload = [
            'name' => 'Mechanical Keyboard',
            'sku' => 'KB-001',
            'description' => 'Clicky.',
            'category' => 'Electronics',
            'price' => 129.99,
            'stock' => 15,
            'status' => 'active',
        ];

        $this->postJson('/api/v1/products', $payload)
            ->assertCreated()
            ->assertJsonPath('data.sku', 'KB-001')
            ->assertJsonPath('data.price', 129.99);

        $this->assertDatabaseHas('products', ['sku' => 'KB-001', 'stock' => 15]);
    }

    public function test_create_validates_input(): void
    {
        Product::factory()->create(['sku' => 'DUP-1']);

        $this->postJson('/api/v1/products', ['sku' => 'DUP-1', 'price' => -1, 'status' => 'nope'])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors(['name', 'sku', 'price', 'stock', 'status']);
    }

    public function test_can_show_product(): void
    {
        $product = Product::factory()->create();

        $this->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id);
    }

    public function test_show_missing_product_returns_json_404(): void
    {
        $this->getJson('/api/v1/products/999999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.']);
    }

    public function test_can_update_product(): void
    {
        $product = Product::factory()->create(['sku' => 'KEEP-1']);

        $this->putJson("/api/v1/products/{$product->id}", ['name' => 'Renamed', 'sku' => 'KEEP-1', 'stock' => 3])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.stock', 3);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Renamed']);
    }

    public function test_can_delete_product(): void
    {
        $product = Product::factory()->create();

        $this->deleteJson("/api/v1/products/{$product->id}")->assertOk();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_cannot_delete_product_with_orders(): void
    {
        $order = Order::factory()->create();

        $this->deleteJson("/api/v1/products/{$order->product_id}")
            ->assertStatus(409)
            ->assertJsonStructure(['message']);
    }
}
