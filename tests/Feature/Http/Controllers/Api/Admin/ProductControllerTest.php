<?php

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->admin()->create());
});

describe('index', function () {
    test('it lists every product including inactive ones', function () {
        Product::factory()->create();
        Product::factory()->inactive()->create(['name' => 'Hidden Lamp']);

        $this->getJson('/api/admin/products')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.name', 'Hidden Lamp')
            ->assertJsonPath('data.0.status', 'inactive');
    });
});

describe('store', function () {
    test('it creates a product with the price converted to cents', function () {
        $this->postJson('/api/admin/products', [
            'name' => 'Desk Lamp',
            'sku' => 'LMP-00001',
            'description' => 'Warm light.',
            'price' => '149.99',
            'stock_quantity' => 12,
        ])
            ->assertCreated()
            ->assertJsonPath('data.price', '149.99')
            ->assertJsonPath('data.status', 'active');

        expect(Product::sole())
            ->sku->toBe('LMP-00001')
            ->price->toBe(14999)
            ->stock_quantity->toBe(12);
    });

    test('it returns 422 for invalid input', function (array $overrides, string $field) {
        Product::factory()->create(['sku' => 'TAKEN-1']);
        $payload = ['name' => 'Desk Lamp', 'sku' => 'LMP-00001', 'price' => '10.00', 'stock_quantity' => 1, ...$overrides];

        $this->postJson('/api/admin/products', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        expect(Product::count())->toBe(1);
    })->with([
        'missing name' => [['name' => null], 'name'],
        'duplicate SKU' => [['sku' => 'TAKEN-1'], 'sku'],
        'SKU with spaces' => [['sku' => 'LMP 1'], 'sku'],
        'zero price' => [['price' => '0'], 'price'],
        'three decimal places' => [['price' => '1.999'], 'price'],
        'price above the column limit' => [['price' => '42949672.96'], 'price'],
        'negative stock' => [['stock_quantity' => -1], 'stock_quantity'],
        'unknown status' => [['status' => 'archived'], 'status'],
    ]);
});

describe('show', function () {
    test('it shows an inactive product', function () {
        $product = Product::factory()->inactive()->create();

        $this->getJson("/api/admin/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');
    });
});

describe('update', function () {
    test('it changes only the submitted fields and refreshes the public listing', function () {
        $product = Product::factory()->create(['name' => 'Desk Lamp', 'price' => 1000]);
        $this->getJson('/api/products')->assertJsonPath('data.0.price', '10.00');

        $this->patchJson("/api/admin/products/{$product->id}", ['price' => '12.50'])
            ->assertOk()
            ->assertJsonPath('data.price', '12.50')
            ->assertJsonPath('data.name', 'Desk Lamp');

        $this->getJson('/api/products')->assertJsonPath('data.0.price', '12.50');
    });

    test('deactivating a product hides it from customers', function () {
        $product = Product::factory()->create();

        $this->patchJson("/api/admin/products/{$product->id}", ['status' => 'inactive'])->assertOk();

        expect($product->fresh()->status)->toBe(ProductStatus::Inactive);
        $this->getJson("/api/products/{$product->id}")->assertNotFound();
    });

    test('it allows keeping the same SKU but rejects another product\'s SKU', function () {
        $product = Product::factory()->create(['sku' => 'LMP-00001']);
        Product::factory()->create(['sku' => 'LMP-00002']);

        $this->patchJson("/api/admin/products/{$product->id}", ['sku' => 'LMP-00001'])->assertOk();

        $this->patchJson("/api/admin/products/{$product->id}", ['sku' => 'LMP-00002'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sku' => 'The sku has already been taken.']);
    });
});
