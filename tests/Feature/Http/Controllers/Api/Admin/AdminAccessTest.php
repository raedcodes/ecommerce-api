<?php

use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

dataset('admin routes', [
    'list products' => ['GET', '/api/admin/products'],
    'create product' => ['POST', '/api/admin/products'],
    'show product' => ['GET', '/api/admin/products/{product}'],
    'update product' => ['PATCH', '/api/admin/products/{product}'],
    'list promotions' => ['GET', '/api/admin/promotions'],
    'create promotion' => ['POST', '/api/admin/promotions'],
    'show promotion' => ['GET', '/api/admin/promotions/{promotion}'],
    'update promotion' => ['PATCH', '/api/admin/promotions/{promotion}'],
    'delete promotion' => ['DELETE', '/api/admin/promotions/{promotion}'],
]);

test('admin routes return 401 without a token', function (string $method, string $uri) {
    $this->json($method, str_replace(['{product}', '{promotion}'], '1', $uri))->assertUnauthorized();
})->with('admin routes');

test('admin routes return 403 to customers and change nothing', function (string $method, string $uri) {
    $product = Product::factory()->create(['price' => 1000]);
    $promotion = Promotion::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->json($method, str_replace(['{product}', '{promotion}'], [$product->id, $promotion->id], $uri), ['price' => '1.00', 'is_active' => false])
        ->assertForbidden()
        ->assertExactJson(['message' => 'This action requires an administrator.', 'code' => 'forbidden']);

    expect($product->fresh()->price)->toBe(1000)
        ->and($promotion->fresh()->is_active)->toBeTrue()
        ->and(Product::count())->toBe(1)
        ->and(Promotion::count())->toBe(1);
})->with('admin routes');
