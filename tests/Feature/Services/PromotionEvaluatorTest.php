<?php

use App\Exceptions\CouponException;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\User;
use App\Services\PromotionEvaluator;
use Illuminate\Support\Carbon;

function evaluator(): PromotionEvaluator
{
    return app(PromotionEvaluator::class);
}

/**
 * Asserts the callback throws a CouponException with the given error code.
 */
function expectCouponError(Closure $callback, string $errorCode, ?string $message = null): void
{
    expect($callback)->toThrow(function (CouponException $exception) use ($errorCode, $message) {
        expect($exception->errorCode)->toBe($errorCode);

        if ($message !== null) {
            expect($exception->getMessage())->toBe($message);
        }
    });
}

function summer20(): Promotion
{
    return Promotion::factory()->percentage(20)->create([
        'code' => 'SUMMER20',
        'min_cart_amount' => 10000,
        'max_discount_amount' => 5000,
    ]);
}

test('SUMMER20 takes 20% off and caps the discount at $50', function (int $subtotal, int $expectedDiscount) {
    expect(evaluator()->discountFor(summer20(), User::factory()->create(), $subtotal))->toBe($expectedDiscount);
})->with([
    'exactly the $100 minimum' => [10000, 2000],
    'just under the cap' => [24995, 4999],
    'exactly at the cap' => [25000, 5000],
    'above the cap' => [30000, 5000],
]);

test('SUMMER20 rejects a cart below the $100 minimum', function () {
    $promotion = summer20();
    $customer = User::factory()->create();

    expect(fn () => evaluator()->discountFor($promotion, $customer, 9999))
        ->toThrow(function (CouponException $exception) {
            expect($exception->errorCode)->toBe('coupon_minimum_not_met')
                ->and($exception->getMessage())->toBe('This coupon requires a minimum cart subtotal of 100.00.')
                ->and($exception->details)->toBe(['minimum' => '100.00', 'subtotal' => '99.99']);
        });
});

test('the discount follows the type, the cap and the subtotal', function (Closure $promotion, int $subtotal, int $expectedDiscount) {
    expect(evaluator()->calculateDiscount($promotion(), $subtotal))->toBe($expectedDiscount);
})->with([
    'percentage rounds half up' => [fn () => Promotion::factory()->percentage(10)->make(), 1005, 101],
    'percentage rounds down below half' => [fn () => Promotion::factory()->percentage(10)->make(), 1004, 100],
    'full percentage equals the subtotal' => [fn () => Promotion::factory()->percentage(100)->make(), 4321, 4321],
    'fixed amount' => [fn () => Promotion::factory()->fixed(1000)->make(), 5000, 1000],
    'fixed amount never exceeds the subtotal' => [fn () => Promotion::factory()->fixed(1000)->make(), 600, 600],
    'fixed amount respects a lower cap' => [fn () => Promotion::factory()->fixed(1000)->make(['max_discount_amount' => 500]), 5000, 500],
]);

test('it rejects a promotion the customer cannot use', function (Closure $promotion, string $errorCode, string $message) {
    $promotion = $promotion();
    $customer = User::factory()->create();

    expectCouponError(fn () => evaluator()->discountFor($promotion, $customer, 10000), $errorCode, $message);
})->with([
    'switched off' => [fn () => Promotion::factory()->inactive()->create(), 'coupon_invalid', 'The coupon code is invalid.'],
    'not started' => [fn () => Promotion::factory()->notStarted()->create(), 'coupon_invalid', 'This coupon is not active yet.'],
    'expired' => [fn () => Promotion::factory()->expired()->create(), 'coupon_expired', 'This coupon has expired.'],
    'global usage limit reached' => [fn () => Promotion::factory()->exhausted()->create(), 'coupon_usage_limit_reached', 'This coupon has reached its usage limit.'],
]);

test('it reports the most fundamental problem first', function () {
    $promotion = Promotion::factory()->inactive()->expired()->exhausted()->create(['min_cart_amount' => 100000]);
    $customer = User::factory()->create();

    expectCouponError(fn () => evaluator()->discountFor($promotion, $customer, 100), 'coupon_invalid');
});

test('it accepts a promotion during its first and last second', function (string $boundary) {
    $this->travelTo(Carbon::parse('2026-08-31 23:59:59.500'));
    $promotion = Promotion::factory()->create([$boundary => '2026-08-31 23:59:59']);

    expect(evaluator()->discountFor($promotion, User::factory()->create(), 10000))->toBe(1000);
})->with(['starts_at', 'ends_at']);

test('it rejects a promotion once its last second has passed', function () {
    $this->travelTo(Carbon::parse('2026-09-01 00:00:00.000'));
    $promotion = Promotion::factory()->create(['ends_at' => '2026-08-31 23:59:59']);
    $customer = User::factory()->create();

    expectCouponError(fn () => evaluator()->discountFor($promotion, $customer, 10000), 'coupon_expired');
});

describe('per-customer limit', function () {
    test('it rejects a customer who has reached the limit', function () {
        $promotion = Promotion::factory()->create(['per_customer_limit' => 1]);
        $customer = User::factory()->create();
        Order::factory()->for($customer)->for($promotion)->create();

        expectCouponError(
            fn () => evaluator()->discountFor($promotion, $customer, 10000),
            'coupon_customer_limit_reached',
            'You have already used this coupon the maximum number of times.',
        );
    });

    test('it does not count cancelled orders or other customers\' orders', function () {
        $promotion = Promotion::factory()->create(['per_customer_limit' => 1]);
        $customer = User::factory()->create();
        Order::factory()->for($customer)->for($promotion)->cancelled()->create();
        Order::factory()->for($promotion)->create();

        expect(evaluator()->discountFor($promotion, $customer, 10000))->toBe(1000);
    });
});
