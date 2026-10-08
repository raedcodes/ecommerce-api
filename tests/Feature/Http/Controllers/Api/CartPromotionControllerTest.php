<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Give the customer a cart with one line worth the given subtotal.
 */
function cartWorth(User $user, int $subtotalCents): Cart
{
    $cart = Cart::factory()->for($user)->create();
    CartItem::factory()->for($cart)->for(Product::factory()->state(['price' => $subtotalCents]))->create(['quantity' => 1]);

    return $cart;
}

describe('store', function () {
    test('it applies an eligible code regardless of case and whitespace', function () {
        $user = User::factory()->create();
        $cart = cartWorth($user, 10000);
        $promotion = Promotion::factory()->percentage(20)->create(['code' => 'SUMMER20', 'min_cart_amount' => 10000, 'max_discount_amount' => 5000]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/promotion', ['code' => ' summer20 '])
            ->assertOk()
            ->assertJsonPath('data.promotion.code', 'SUMMER20')
            ->assertJsonPath('data.promotion.is_valid', true)
            ->assertJsonPath('data.subtotal', '100.00')
            ->assertJsonPath('data.discount', '20.00')
            ->assertJsonPath('data.total', '80.00');

        expect($cart->fresh()->promotion_id)->toBe($promotion->id);
    });

    test('it returns 422 coupon_invalid for an unknown code', function () {
        $user = User::factory()->create();
        $cart = cartWorth($user, 10000);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/promotion', ['code' => 'NOPE'])
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'The coupon code is invalid.', 'code' => 'coupon_invalid']);

        expect($cart->fresh()->promotion_id)->toBeNull();
    });

    test('it returns the eligibility error and does not attach the code', function () {
        $user = User::factory()->create();
        $cart = cartWorth($user, 10000);
        Promotion::factory()->expired()->create(['code' => 'OLD10']);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/promotion', ['code' => 'OLD10'])
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'This coupon has expired.', 'code' => 'coupon_expired']);

        expect($cart->fresh()->promotion_id)->toBeNull();
    });

    test('it returns 422 coupon_minimum_not_met with the minimum and the current subtotal', function () {
        $user = User::factory()->create();
        cartWorth($user, 9999);
        Promotion::factory()->create(['code' => 'SUMMER20', 'min_cart_amount' => 10000]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/promotion', ['code' => 'SUMMER20'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'coupon_minimum_not_met')
            ->assertJsonPath('details', ['minimum' => '100.00', 'subtotal' => '99.99']);
    });

    test('it returns 422 cart_empty when there is nothing to discount', function (bool $hasCart) {
        $user = User::factory()->create();
        if ($hasCart) {
            Cart::factory()->for($user)->create();
        }
        Promotion::factory()->create(['code' => 'SUMMER20']);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/promotion', ['code' => 'SUMMER20'])
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'Your cart is empty.', 'code' => 'cart_empty']);
    })->with([
        'no cart yet' => [false],
        'cart without items' => [true],
    ]);

    test('it returns 422 when the code is missing', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/cart/promotion', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    });
});

describe('destroy', function () {
    test('it removes the applied code', function () {
        $user = User::factory()->create();
        $cart = cartWorth($user, 10000);
        $cart->promotion()->associate(Promotion::factory()->create())->save();
        Sanctum::actingAs($user);

        $this->deleteJson('/api/cart/promotion')
            ->assertOk()
            ->assertJsonPath('data.promotion', null)
            ->assertJsonPath('data.discount', '0.00')
            ->assertJsonPath('data.total', '100.00');

        expect($cart->fresh()->promotion_id)->toBeNull();
    });

    test('it returns an empty cart when the customer has none', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/cart/promotion')
            ->assertOk()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.promotion', null);

        expect(Cart::count())->toBe(0);
    });
});
