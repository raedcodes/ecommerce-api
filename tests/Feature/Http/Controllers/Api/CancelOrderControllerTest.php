<?php

use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * An order for the customer holding the given quantity of the product, as checkout would create it.
 */
function orderOf(User $user, Product $product, int $quantity, array $orderState = []): Order
{
    $order = Order::factory()->for($user)->create($orderState);
    OrderItem::factory()->for($order)->for($product)->create(['quantity' => $quantity]);

    return $order;
}

test('it returns 401 without a token', function () {
    $this->postJson('/api/orders/1/cancel')->assertUnauthorized();
});

test('cancelling a placed order returns its stock and its coupon use', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 30000, 'stock_quantity' => 5]);
    $promotion = Promotion::factory()->percentage(20)->create(['code' => 'SUMMER20', 'per_customer_limit' => 1]);
    CartItem::factory()->for(Cart::factory()->for($user)->for($promotion))->for($product)->create(['quantity' => 2]);
    Sanctum::actingAs($user);
    $orderId = $this->postJson('/api/checkout')->assertCreated()->json('data.id');
    expect($product->fresh()->stock_quantity)->toBe(3)->and($promotion->fresh()->times_used)->toBe(1);

    $this->postJson("/api/orders/{$orderId}/cancel")
        ->assertOk()
        ->assertJsonPath('data.id', $orderId)
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancelled_at', fn (?string $cancelledAt) => $cancelledAt !== null);

    expect($product->fresh()->stock_quantity)->toBe(5)
        ->and($promotion->fresh()->times_used)->toBe(0);

    // The per-customer use is given back, so the customer can apply the code again.
    CartItem::factory()->for($user->cart)->for($product)->create(['quantity' => 1]);
    $this->postJson('/api/cart/promotion', ['code' => 'SUMMER20'])->assertOk();
});

test('it cancels an order that is still cancellable', function (string $status) {
    $user = User::factory()->create();
    $product = Product::factory()->create(['stock_quantity' => 3]);
    $order = orderOf($user, $product, 2, ['status' => $status]);
    Sanctum::actingAs($user);

    $this->postJson("/api/orders/{$order->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->fresh()->cancelled_at)->not->toBeNull()
        ->and($product->fresh()->stock_quantity)->toBe(5);
})->with(['pending', 'processing']);

test('cancelling again changes nothing and restores stock only once', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['stock_quantity' => 3]);
    $order = orderOf($user, $product, 2);
    Sanctum::actingAs($user);

    $this->postJson("/api/orders/{$order->id}/cancel")->assertOk();
    $cancelledAt = $order->fresh()->cancelled_at;
    $this->travel(5)->minutes();

    $this->postJson("/api/orders/{$order->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($product->fresh()->stock_quantity)->toBe(5)
        ->and($order->fresh()->cancelled_at->equalTo($cancelledAt))->toBeTrue();
});

test('it returns 409 invalid_order_status and changes nothing once the order has shipped', function (string $status) {
    $user = User::factory()->create();
    $product = Product::factory()->create(['stock_quantity' => 3]);
    $order = orderOf($user, $product, 2, ['status' => $status]);
    Sanctum::actingAs($user);

    $this->postJson("/api/orders/{$order->id}/cancel")
        ->assertConflict()
        ->assertExactJson([
            'message' => "Orders that are {$status} can no longer be cancelled.",
            'code' => 'invalid_order_status',
            'details' => ['status' => $status],
        ]);

    expect($order->fresh()->status->value)->toBe($status)
        ->and($product->fresh()->stock_quantity)->toBe(3);
})->with(['shipped', 'delivered']);

test('it returns 403 for another customer\'s order and changes nothing', function () {
    $product = Product::factory()->create(['stock_quantity' => 3]);
    $othersOrder = orderOf(User::factory()->create(), $product, 2);
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/orders/{$othersOrder->id}/cancel")
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');

    expect($othersOrder->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($product->fresh()->stock_quantity)->toBe(3);
});

test('it returns 404 order_not_found for an order that does not exist', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/orders/999999/cancel')
        ->assertNotFound()
        ->assertJsonPath('code', 'order_not_found');
});

test('it cancels an order whose product has since been deleted', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $order = orderOf($user, $product, 2);
    $product->delete();
    Sanctum::actingAs($user);

    $this->postJson("/api/orders/{$order->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.items.0.product_id', null);
});

test('it refreshes the cached product listing with the restored stock', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['stock_quantity' => 3]);
    $order = orderOf($user, $product, 2);
    $this->getJson('/api/products')->assertJsonPath('data.0.stock_quantity', 3);
    Sanctum::actingAs($user);

    $this->postJson("/api/orders/{$order->id}/cancel")->assertOk();

    $this->getJson('/api/products')->assertJsonPath('data.0.stock_quantity', 5);
});
