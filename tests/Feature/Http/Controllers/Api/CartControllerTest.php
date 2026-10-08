<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('cart endpoints return 401 without a token', function (string $method, string $uri) {
    $this->json($method, $uri)
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
})->with([
    'view cart' => ['GET', '/api/cart'],
    'add item' => ['POST', '/api/cart/items'],
    'update item' => ['PATCH', '/api/cart/items/1'],
    'remove item' => ['DELETE', '/api/cart/items/1'],
    'apply promotion' => ['POST', '/api/cart/promotion'],
    'remove promotion' => ['DELETE', '/api/cart/promotion'],
]);

test('it returns an empty cart without creating one for a customer who has not added anything', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/cart')
        ->assertOk()
        ->assertJsonPath('data.items', [])
        ->assertJsonPath('data.item_count', 0)
        ->assertJsonPath('data.subtotal', '0.00')
        ->assertJsonPath('data.total', '0.00');

    expect(Cart::count())->toBe(0);
});

test('it prices items at the current product price', function () {
    $user = User::factory()->create();
    $cart = Cart::factory()->for($user)->create();
    $lamp = Product::factory()->create(['name' => 'Desk Lamp', 'price' => 1000]);
    $mug = Product::factory()->create(['name' => 'Mug', 'price' => 2550]);
    CartItem::factory()->for($cart)->for($lamp)->create(['quantity' => 2]);
    CartItem::factory()->for($cart)->for($mug)->create(['quantity' => 1]);
    Sanctum::actingAs($user);

    $this->getJson('/api/cart')
        ->assertOk()
        ->assertJsonPath('data.items.0.name', 'Desk Lamp')
        ->assertJsonPath('data.items.0.unit_price', '10.00')
        ->assertJsonPath('data.items.0.line_total', '20.00')
        ->assertJsonPath('data.item_count', 3)
        ->assertJsonPath('data.subtotal', '45.50')
        ->assertJsonPath('data.total', '45.50');

    $lamp->update(['price' => 1200]);

    $this->getJson('/api/cart')
        ->assertJsonPath('data.items.0.unit_price', '12.00')
        ->assertJsonPath('data.subtotal', '49.50');
});

test('it flags items that can no longer be bought', function (array $productState, int $expectedAvailable) {
    $user = User::factory()->create();
    $product = Product::factory()->create($productState);
    CartItem::factory()->for(Cart::factory()->for($user))->for($product)->create(['quantity' => 2]);
    Sanctum::actingAs($user);

    $this->getJson('/api/cart')
        ->assertOk()
        ->assertJsonPath('data.items.0.is_available', false)
        ->assertJsonPath('data.items.0.available_quantity', $expectedAvailable);
})->with([
    'stock dropped below the quantity' => [['stock_quantity' => 1], 1],
    'product deactivated' => [['stock_quantity' => 10, 'status' => 'inactive'], 0],
]);

test('it does not show items from another customer\'s cart', function () {
    CartItem::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/cart')
        ->assertOk()
        ->assertJsonPath('data.items', []);
});

test('it shows the discount from the applied promotion', function () {
    $user = User::factory()->create();
    $promotion = Promotion::factory()->fixed(1500)->create(['code' => 'TAKE15']);
    $cart = Cart::factory()->for($user)->for($promotion)->create();
    CartItem::factory()->for($cart)->for(Product::factory()->state(['price' => 5000]))->create(['quantity' => 1]);
    Sanctum::actingAs($user);

    $this->getJson('/api/cart')
        ->assertOk()
        ->assertJsonPath('data.promotion', ['code' => 'TAKE15', 'type' => 'fixed', 'is_valid' => true, 'error' => null])
        ->assertJsonPath('data.subtotal', '50.00')
        ->assertJsonPath('data.discount', '15.00')
        ->assertJsonPath('data.total', '35.00');
});

test('it reports an applied promotion that is no longer valid and does not discount', function () {
    $user = User::factory()->create();
    $promotion = Promotion::factory()->create(['code' => 'BIG100', 'min_cart_amount' => 10000]);
    $cart = Cart::factory()->for($user)->for($promotion)->create();
    CartItem::factory()->for($cart)->for(Product::factory()->state(['price' => 5000]))->create(['quantity' => 1]);
    Sanctum::actingAs($user);

    $this->getJson('/api/cart')
        ->assertOk()
        ->assertJsonPath('data.promotion.is_valid', false)
        ->assertJsonPath('data.promotion.error.code', 'coupon_minimum_not_met')
        ->assertJsonPath('data.discount', '0.00')
        ->assertJsonPath('data.total', '50.00');
});
