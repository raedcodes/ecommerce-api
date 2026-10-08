<?php

use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Process\Pool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;

/*
 * These tests start real, separate PHP processes that hit the MySQL test database at the same
 * moment, so they exercise the actual row locks rather than one process taking turns.
 */

afterEach(function () {
    // Leave the shared test database empty for the transaction-based feature tests.
    $this->truncateTablesForAllConnections();
});

/**
 * Run each [action, id] job in its own process, all starting at the same instant.
 *
 * @param  list<array{0: 'checkout'|'cancel', 1: int}>  $jobs
 * @return Collection<int, array<string, mixed>>
 */
function runConcurrently(array $jobs): Collection
{
    $startAt = (string) (microtime(true) + 2.0);
    $environment = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => config('database.default'),
        'DB_DATABASE' => config('database.connections.'.config('database.default').'.database'),
        'CACHE_STORE' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'MAIL_MAILER' => 'array',
        'BROADCAST_CONNECTION' => 'null',
    ];

    $results = Process::pool(function (Pool $pool) use ($jobs, $startAt, $environment) {
        foreach ($jobs as [$action, $id]) {
            $pool->path(base_path())
                ->env($environment)
                ->timeout(60)
                ->command([PHP_BINARY, 'tests/Concurrency/worker.php', $action, $startAt, (string) $id]);
        }
    })->start()->wait();

    return $results->collect()->map(function ($result) {
        expect($result->successful())->toBeTrue($result->errorOutput());

        return json_decode(trim($result->output()), true, flags: JSON_THROW_ON_ERROR);
    })->values();
}

function customerWithCart(Product $product, int $quantity, ?Promotion $promotion = null): User
{
    $customer = User::factory()->create();
    CartItem::factory()
        ->for(Cart::factory()->for($customer)->state(['promotion_id' => $promotion?->id]))
        ->for($product)
        ->create(['quantity' => $quantity]);

    return $customer;
}

test('two customers racing for the same stock can never oversell it', function () {
    $product = Product::factory()->create(['stock_quantity' => 5]);
    $customerA = customerWithCart($product, 4);
    $customerB = customerWithCart($product, 3);

    $results = runConcurrently([['checkout', $customerA->id], ['checkout', $customerB->id]]);

    expect($results->where('result', 'ok'))->toHaveCount(1)
        ->and($results->where('code', 'insufficient_stock'))->toHaveCount(1);

    $winningQuantity = Order::with('items')->sole()->items->sole()->quantity;
    expect($product->fresh()->stock_quantity)
        ->toBe(5 - $winningQuantity)
        ->toBeGreaterThanOrEqual(0);
});

test('ten buyers for five units produce exactly five orders and zero stock', function () {
    $product = Product::factory()->create(['stock_quantity' => 5]);
    $jobs = collect(range(1, 10))->map(fn () => ['checkout', customerWithCart($product, 1)->id])->all();

    $results = runConcurrently($jobs);

    expect($results->where('result', 'ok'))->toHaveCount(5)
        ->and($results->where('code', 'insufficient_stock'))->toHaveCount(5)
        ->and(Order::count())->toBe(5)
        ->and($product->fresh()->stock_quantity)->toBe(0);
});

test('a coupon with a usage limit of one is redeemed exactly once', function () {
    $product = Product::factory()->create(['stock_quantity' => 100]);
    $promotion = Promotion::factory()->create(['usage_limit' => 1]);
    $jobs = collect(range(1, 5))->map(fn () => ['checkout', customerWithCart($product, 1, $promotion)->id])->all();

    $results = runConcurrently($jobs);

    expect($results->where('result', 'ok'))->toHaveCount(1)
        ->and($results->where('code', 'coupon_usage_limit_reached'))->toHaveCount(4)
        ->and($promotion->fresh()->times_used)->toBe(1)
        ->and(Order::whereNotNull('promotion_id')->count())->toBe(1);
});

test('a double-clicked checkout places a single order', function () {
    $product = Product::factory()->create(['stock_quantity' => 5]);
    $customer = customerWithCart($product, 2);

    $results = runConcurrently([['checkout', $customer->id], ['checkout', $customer->id]]);

    expect($results->where('result', 'ok'))->toHaveCount(1)
        ->and($results->where('code', 'cart_empty'))->toHaveCount(1)
        ->and(Order::count())->toBe(1)
        ->and($product->fresh()->stock_quantity)->toBe(3);
});

test('simultaneous cancellations restore stock exactly once', function () {
    $product = Product::factory()->create(['stock_quantity' => 3]);
    $order = Order::factory()->create();
    OrderItem::factory()->for($order)->for($product)->create(['quantity' => 2]);

    $results = runConcurrently([['cancel', $order->id], ['cancel', $order->id], ['cancel', $order->id]]);

    expect($results->where('result', 'ok'))->toHaveCount(3)
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($product->fresh()->stock_quantity)->toBe(5);
});

test('a cancellation and a checkout on the same product neither deadlock nor lose stock', function () {
    $product = Product::factory()->create(['stock_quantity' => 3]);
    $order = Order::factory()->create();
    OrderItem::factory()->for($order)->for($product)->create(['quantity' => 2]);
    $buyer = customerWithCart($product, 4);

    $results = runConcurrently([['cancel', $order->id], ['checkout', $buyer->id]]);

    expect($results->where('result', 'error'))->toBeEmpty()
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled);

    // The checkout succeeds only if the cancellation's stock was back first; either way nothing is lost.
    $checkoutSucceeded = $results->last()['result'] === 'ok';
    expect($product->fresh()->stock_quantity)->toBe($checkoutSucceeded ? 1 : 5);
});
