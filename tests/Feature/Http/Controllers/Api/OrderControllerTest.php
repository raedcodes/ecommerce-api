<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('order endpoints return 401 without a token', function (string $uri) {
    $this->getJson($uri)->assertUnauthorized();
})->with([
    'list' => ['/api/orders'],
    'show' => ['/api/orders/1'],
]);

describe('index', function () {
    test('it lists only the customer\'s own orders, newest first, with their items', function () {
        $user = User::factory()->create();
        $older = Order::factory()->for($user)->create(['created_at' => now()->subDay()]);
        $newer = Order::factory()->for($user)->create(['created_at' => now()]);
        OrderItem::factory()->for($newer)->create(['product_name' => 'Desk Lamp']);
        Order::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$newer->id, $older->id])
            ->assertJsonPath('data.0.items.0.name', 'Desk Lamp')
            ->assertJsonPath('meta.total', 2);
    });

    test('it paginates with the requested page size', function () {
        $user = User::factory()->create();
        Order::factory()->count(3)->for($user)->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/orders?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2);
    });

    test('it returns 422 for a page size above 100', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/orders?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    });
});

describe('show', function () {
    test('it returns the order with its items', function () {
        $user = User::factory()->create();
        $order = Order::factory()->for($user)->create(['subtotal' => 6000, 'discount_amount' => 1000, 'total' => 5000, 'promotion_code' => 'TAKE10']);
        OrderItem::factory()->for($order)->create(['product_name' => 'Desk Lamp', 'product_sku' => 'LMP-00001', 'unit_price' => 3000, 'quantity' => 2, 'line_total' => 6000]);
        Sanctum::actingAs($user);

        $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.subtotal', '60.00')
            ->assertJsonPath('data.discount', '10.00')
            ->assertJsonPath('data.total', '50.00')
            ->assertJsonPath('data.promotion_code', 'TAKE10')
            ->assertJsonPath('data.items.0', [
                'id' => $order->items()->value('id'),
                'product_id' => $order->items()->value('product_id'),
                'name' => 'Desk Lamp',
                'sku' => 'LMP-00001',
                'unit_price' => '30.00',
                'quantity' => 2,
                'line_total' => '60.00',
            ]);
    });

    test('it returns 403 for another customer\'s order', function () {
        $othersOrder = Order::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/orders/{$othersOrder->id}")
            ->assertForbidden()
            ->assertExactJson(['message' => 'You do not have access to this order.', 'code' => 'forbidden']);
    });

    test('it returns 404 order_not_found for an order that does not exist', function (string $id) {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/orders/{$id}")
            ->assertNotFound()
            ->assertJsonPath('code', 'order_not_found');
    })->with([
        'unknown id' => ['999999'],
        'larger than a 64-bit integer' => ['99999999999999999999999'],
    ]);

    test('past orders keep the name, SKU and price paid after the product changes', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Desk Lamp', 'sku' => 'LMP-00001', 'price' => 2500, 'stock_quantity' => 5]);
        CartItem::factory()->for(Cart::factory()->for($user))->for($product)->create(['quantity' => 2]);
        Sanctum::actingAs($user);
        $orderId = $this->postJson('/api/checkout')->assertCreated()->json('data.id');

        $product->update(['name' => 'Desk Lamp v2', 'sku' => 'LMP-00002', 'price' => 9900]);

        $this->getJson("/api/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.items.0.name', 'Desk Lamp')
            ->assertJsonPath('data.items.0.sku', 'LMP-00001')
            ->assertJsonPath('data.items.0.unit_price', '25.00')
            ->assertJsonPath('data.total', '50.00');
    });
});
