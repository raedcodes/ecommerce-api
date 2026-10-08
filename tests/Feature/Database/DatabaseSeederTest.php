<?php

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Database\Seeders\ProductSeeder;

test('the demo data can be seeded repeatedly without duplicates', function () {
    $this->seed();
    $counts = fn () => [User::count(), Product::count(), Promotion::count(), Order::count()];
    $firstRun = $counts();

    $this->seed();

    expect($counts())->toBe($firstRun)
        ->and($firstRun)->toBe([3, 7 + ProductSeeder::FILLER_COUNT, 6, 5]);
});

test('it seeds one admin and two customers with the documented credentials', function () {
    $this->seed();

    expect(User::where('is_admin', true)->pluck('email')->all())->toBe(['admin@example.com']);

    $this->postJson('/api/auth/login', ['email' => 'customer@example.com', 'password' => 'password'])->assertOk();
});

test('it seeds a promotion for every coupon rule, including the SUMMER20 example', function () {
    $this->seed();

    $summer20 = Promotion::where('code', 'SUMMER20')->sole();

    expect($summer20->only(['value', 'min_cart_amount', 'max_discount_amount']))
        ->toBe(['value' => 20, 'min_cart_amount' => 10000, 'max_discount_amount' => 5000])
        ->and(Promotion::pluck('code')->sort()->values()->all())
        ->toBe(['EXPIRED5', 'FUTURE15', 'LIMITED1', 'PAUSED25', 'SUMMER20', 'WELCOME10'])
        ->and(Promotion::where('code', 'LIMITED1')->sole()->times_used)->toBe(1);
});

test('it seeds inactive and out-of-stock products and orders in every status', function () {
    $this->seed();

    expect(Product::where('status', ProductStatus::Inactive)->count())->toBeGreaterThan(0)
        ->and(Product::where('stock_quantity', 0)->count())->toBeGreaterThan(0)
        ->and(User::where('email', 'customer2@example.com')->sole()->orders()->pluck('status')->all())
        ->toEqualCanonicalizing(OrderStatus::cases());
});

test('the seeded customer can apply SUMMER20 and check out the seeded cart', function () {
    $this->seed();
    $token = $this->postJson('/api/auth/login', ['email' => 'customer@example.com', 'password' => 'password'])->json('data.token');

    $this->withToken($token)->postJson('/api/cart/promotion', ['code' => 'SUMMER20'])
        ->assertOk()
        ->assertJsonPath('data.subtotal', '154.00')
        ->assertJsonPath('data.discount', '30.80');

    $this->withToken($token)->postJson('/api/checkout')
        ->assertCreated()
        ->assertJsonPath('data.total', '123.20');
});
