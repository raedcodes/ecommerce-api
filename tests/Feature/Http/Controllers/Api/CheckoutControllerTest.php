<?php

use App\Events\OrderPlaced;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use App\Notifications\OrderConfirmation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

/**
 * Give the customer a cart holding the given [product, quantity] lines.
 *
 * @param  list<array{0: Product, 1: int}>  $lines
 */
function checkoutCart(User $user, array $lines, ?Promotion $promotion = null): Cart
{
    $cart = Cart::factory()->for($user)->create(['promotion_id' => $promotion?->id]);

    foreach ($lines as [$product, $quantity]) {
        CartItem::factory()->for($cart)->for($product)->create(['quantity' => $quantity]);
    }

    return $cart;
}

test('it returns 401 without a token', function () {
    $this->postJson('/api/checkout')->assertUnauthorized();
});

test('it turns the cart into an order with a snapshot of each product', function () {
    $user = User::factory()->create();
    $lamp = Product::factory()->create(['name' => 'Desk Lamp', 'sku' => 'LMP-00001', 'price' => 2500, 'stock_quantity' => 10]);
    $mug = Product::factory()->create(['name' => 'Mug', 'sku' => 'MUG-00001', 'price' => 1000, 'stock_quantity' => 5]);
    $cart = checkoutCart($user, [[$lamp, 2], [$mug, 1]]);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/checkout')
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.subtotal', '60.00')
        ->assertJsonPath('data.discount', '0.00')
        ->assertJsonPath('data.total', '60.00')
        ->assertJsonPath('data.items.0.name', 'Desk Lamp')
        ->assertJsonPath('data.items.0.unit_price', '25.00')
        ->assertJsonPath('data.items.0.line_total', '50.00');

    $order = Order::with('items')->findOrFail($response->json('data.id'));
    expect($order->user_id)->toBe($user->id)
        ->and($order->total)->toBe(6000)
        ->and($order->items->map->only(['product_id', 'product_name', 'product_sku', 'unit_price', 'quantity', 'line_total'])->all())
        ->toBe([
            ['product_id' => $lamp->id, 'product_name' => 'Desk Lamp', 'product_sku' => 'LMP-00001', 'unit_price' => 2500, 'quantity' => 2, 'line_total' => 5000],
            ['product_id' => $mug->id, 'product_name' => 'Mug', 'product_sku' => 'MUG-00001', 'unit_price' => 1000, 'quantity' => 1, 'line_total' => 1000],
        ])
        ->and($lamp->fresh()->stock_quantity)->toBe(8)
        ->and($mug->fresh()->stock_quantity)->toBe(4)
        ->and($cart->items()->count())->toBe(0);
});

test('it applies the cart promotion, records its code and counts the use', function () {
    $user = User::factory()->create();
    $promotion = Promotion::factory()->percentage(20)->create(['code' => 'SUMMER20', 'min_cart_amount' => 10000, 'max_discount_amount' => 5000]);
    $cart = checkoutCart($user, [[Product::factory()->create(['price' => 30000, 'stock_quantity' => 1]), 1]], $promotion);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/checkout')
        ->assertCreated()
        ->assertJsonPath('data.subtotal', '300.00')
        ->assertJsonPath('data.discount', '50.00')
        ->assertJsonPath('data.total', '250.00')
        ->assertJsonPath('data.promotion_code', 'SUMMER20');

    expect(Order::findOrFail($response->json('data.id'))->promotion_id)->toBe($promotion->id)
        ->and($promotion->fresh()->times_used)->toBe(1)
        ->and($cart->fresh()->promotion_id)->toBeNull();
});

test('it charges current prices and ignores prices or totals sent by the client', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['price' => 1000, 'stock_quantity' => 5]);
    checkoutCart($user, [[$product, 2]]);
    $product->update(['price' => 1500]);
    Sanctum::actingAs($user);

    $this->postJson('/api/checkout', ['subtotal' => '0.01', 'total' => '0.01', 'items' => [['product_id' => $product->id, 'unit_price' => '0.01']]])
        ->assertCreated()
        ->assertJsonPath('data.items.0.unit_price', '15.00')
        ->assertJsonPath('data.total', '30.00');
});

test('it returns 422 cart_empty and creates no order', function (bool $hasCart) {
    $user = User::factory()->create();
    if ($hasCart) {
        Cart::factory()->for($user)->create();
    }
    Sanctum::actingAs($user);

    $this->postJson('/api/checkout')
        ->assertUnprocessable()
        ->assertExactJson(['message' => 'Your cart is empty.', 'code' => 'cart_empty']);

    expect(Order::count())->toBe(0);
})->with([
    'no cart yet' => [false],
    'cart without items' => [true],
]);

test('it returns 422 insufficient_stock and changes nothing when any item is unavailable', function (array $shortProductState, int $expectedAvailable) {
    $user = User::factory()->create();
    $available = Product::factory()->create(['stock_quantity' => 10]);
    $short = Product::factory()->create(['name' => 'Desk Lamp', ...$shortProductState]);
    $cart = checkoutCart($user, [[$available, 2], [$short, 3]]);
    Sanctum::actingAs($user);

    $this->postJson('/api/checkout')
        ->assertUnprocessable()
        ->assertJsonPath('code', 'insufficient_stock')
        ->assertJsonPath('details.items', [[
            'product_id' => $short->id,
            'name' => 'Desk Lamp',
            'requested' => 3,
            'available' => $expectedAvailable,
        ]]);

    expect(Order::count())->toBe(0)
        ->and($available->fresh()->stock_quantity)->toBe(10)
        ->and($short->fresh()->stock_quantity)->toBe($shortProductState['stock_quantity'])
        ->and($cart->items()->count())->toBe(2);
})->with([
    'stock dropped after it was added' => [['stock_quantity' => 2], 2],
    'product deactivated after it was added' => [['stock_quantity' => 10, 'status' => 'inactive'], 0],
]);

test('it lists every short item at once', function () {
    $user = User::factory()->create();
    checkoutCart($user, [
        [Product::factory()->create(['stock_quantity' => 1]), 2],
        [Product::factory()->create(['stock_quantity' => 0]), 1],
    ]);
    Sanctum::actingAs($user);

    $this->postJson('/api/checkout')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Some items exceed the available stock.')
        ->assertJsonCount(2, 'details.items');
});

test('it returns the coupon error and changes nothing when the promotion is no longer valid', function () {
    $user = User::factory()->create();
    $promotion = Promotion::factory()->expired()->create();
    $product = Product::factory()->create(['stock_quantity' => 5]);
    $cart = checkoutCart($user, [[$product, 1]], $promotion);
    Sanctum::actingAs($user);

    $this->postJson('/api/checkout')
        ->assertUnprocessable()
        ->assertExactJson(['message' => 'This coupon has expired.', 'code' => 'coupon_expired']);

    expect(Order::count())->toBe(0)
        ->and($product->fresh()->stock_quantity)->toBe(5)
        ->and($promotion->fresh()->times_used)->toBe(0)
        ->and($cart->fresh()->promotion_id)->toBe($promotion->id)
        ->and($cart->items()->count())->toBe(1);
});

describe('idempotency', function () {
    test('replaying an Idempotency-Key returns the original order without placing another', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 5]);
        $cart = checkoutCart($user, [[$product, 2]]);
        Sanctum::actingAs($user);

        $orderId = $this->withHeader('Idempotency-Key', 'checkout-abc-123')
            ->postJson('/api/checkout')
            ->assertCreated()
            ->json('data.id');

        // The customer starts a new cart before the retry arrives; the retry must not touch it.
        CartItem::factory()->for($cart)->for($product)->create(['quantity' => 1]);

        $this->withHeader('Idempotency-Key', 'checkout-abc-123')
            ->postJson('/api/checkout')
            ->assertOk()
            ->assertJsonPath('data.id', $orderId);

        expect(Order::count())->toBe(1)
            ->and($product->fresh()->stock_quantity)->toBe(3)
            ->and($cart->items()->count())->toBe(1);
    });

    test('another customer\'s key does not replay their order', function () {
        $other = User::factory()->create();
        Order::factory()->for($other)->create(['idempotency_key' => 'shared-key']);
        $user = User::factory()->create();
        checkoutCart($user, [[Product::factory()->create(['stock_quantity' => 5]), 1]]);
        Sanctum::actingAs($user);

        $this->withHeader('Idempotency-Key', 'shared-key')
            ->postJson('/api/checkout')
            ->assertCreated()
            ->assertJsonPath('data.id', fn (int $id) => $id !== $other->orders()->value('id'));

        expect($user->orders()->count())->toBe(1);
    });

    test('it returns 422 for a malformed Idempotency-Key', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->withHeader('Idempotency-Key', 'not a valid key!')
            ->postJson('/api/checkout')
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'idempotency_key' => 'The Idempotency-Key header may only contain letters, numbers, dashes, underscores, colons and dots.',
            ]);
    });
});

describe('after the order is placed', function () {
    test('it dispatches OrderPlaced for the new order', function () {
        Event::fake([OrderPlaced::class]);
        $user = User::factory()->create();
        checkoutCart($user, [[Product::factory()->create(['stock_quantity' => 5]), 1]]);
        Sanctum::actingAs($user);

        $orderId = $this->postJson('/api/checkout')->assertCreated()->json('data.id');

        Event::assertDispatchedTimes(OrderPlaced::class, 1);
        Event::assertDispatched(OrderPlaced::class, fn (OrderPlaced $event) => $event->order->id === $orderId);
    });

    test('it does not dispatch OrderPlaced when checkout fails', function () {
        Event::fake([OrderPlaced::class]);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/checkout')->assertUnprocessable();

        Event::assertNotDispatched(OrderPlaced::class);
    });

    test('it emails the customer an order confirmation', function () {
        Notification::fake();
        $user = User::factory()->create();
        checkoutCart($user, [[Product::factory()->create(['stock_quantity' => 5]), 1]]);
        Sanctum::actingAs($user);

        $orderId = $this->postJson('/api/checkout')->assertCreated()->json('data.id');

        Notification::assertSentTo($user, OrderConfirmation::class, fn (OrderConfirmation $notification) => $notification->order->id === $orderId);
    });

    test('it refreshes the cached product listing with the reduced stock', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 5]);
        checkoutCart($user, [[$product, 2]]);
        $this->getJson('/api/products')->assertJsonPath('data.0.stock_quantity', 5);
        Sanctum::actingAs($user);

        $this->postJson('/api/checkout')->assertCreated();

        $this->getJson('/api/products')->assertJsonPath('data.0.stock_quantity', 3);
    });
});
