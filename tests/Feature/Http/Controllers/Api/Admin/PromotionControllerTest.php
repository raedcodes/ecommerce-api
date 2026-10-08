<?php

use App\Enums\PromotionType;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->admin()->create());
});

describe('store', function () {
    test('it creates the SUMMER20 example from the requirements', function () {
        $this->postJson('/api/admin/promotions', [
            'code' => ' summer20 ',
            'type' => 'percentage',
            'value' => 20,
            'min_cart_amount' => '100.00',
            'max_discount_amount' => '50.00',
            'starts_at' => '2026-06-01 00:00:00',
            'ends_at' => '2026-08-31 23:59:59',
            'usage_limit' => 1000,
            'per_customer_limit' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'SUMMER20')
            ->assertJsonPath('data.value', 20)
            ->assertJsonPath('data.min_cart_amount', '100.00')
            ->assertJsonPath('data.max_discount_amount', '50.00')
            ->assertJsonPath('data.times_used', 0)
            ->assertJsonPath('data.is_active', true);

        expect(Promotion::sole())
            ->type->toBe(PromotionType::Percentage)
            ->value->toBe(20)
            ->min_cart_amount->toBe(10000)
            ->max_discount_amount->toBe(5000);
    });

    test('it stores a fixed amount in cents', function () {
        $this->postJson('/api/admin/promotions', ['code' => 'TAKE10', 'type' => 'fixed', 'value' => '10.50'])
            ->assertCreated()
            ->assertJsonPath('data.value', '10.50');

        expect(Promotion::sole()->value)->toBe(1050);
    });

    test('it ignores an attempt to set the usage counter', function () {
        $this->postJson('/api/admin/promotions', ['code' => 'TAKE10', 'type' => 'fixed', 'value' => '10', 'times_used' => 999])
            ->assertCreated()
            ->assertJsonPath('data.times_used', 0);
    });

    test('it returns 422 for invalid input', function (array $overrides, string $field, ?string $message = null) {
        Promotion::factory()->create(['code' => 'TAKEN']);
        $payload = ['code' => 'NEW10', 'type' => 'percentage', 'value' => 10, ...$overrides];

        $this->postJson('/api/admin/promotions', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($message === null ? $field : [$field => $message]);

        expect(Promotion::count())->toBe(1);
    })->with([
        'missing type' => [['type' => null], 'type'],
        'unknown type' => [['type' => 'bogo'], 'type'],
        'duplicate code in another case' => [['code' => 'taken'], 'code'],
        'code with spaces' => [['code' => 'SUMMER 20'], 'code'],
        'percentage above 100' => [['value' => 101], 'value', 'A percentage value must be a whole number between 1 and 100.'],
        'fractional percentage' => [['value' => '12.5'], 'value', 'A percentage value must be a whole number between 1 and 100.'],
        'zero fixed amount' => [['type' => 'fixed', 'value' => '0'], 'value'],
        'end before start' => [['starts_at' => '2026-08-01', 'ends_at' => '2026-07-01'], 'ends_at', 'The end date must be after the start date.'],
        'zero usage limit' => [['usage_limit' => 0], 'usage_limit'],
    ]);
});

describe('update', function () {
    test('it changes only the submitted fields', function () {
        $promotion = Promotion::factory()->percentage(20)->create(['code' => 'SUMMER20']);

        $this->patchJson("/api/admin/promotions/{$promotion->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.code', 'SUMMER20')
            ->assertJsonPath('data.value', 20);
    });

    test('it checks a new end date against the stored start date', function () {
        $promotion = Promotion::factory()->create(['starts_at' => '2026-06-01 00:00:00', 'ends_at' => '2026-08-31 23:59:59']);

        $this->patchJson("/api/admin/promotions/{$promotion->id}", ['ends_at' => '2026-05-01 00:00:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ends_at' => 'The end date must be after the start date.']);
    });

    test('it requires a new value when the type changes', function () {
        $promotion = Promotion::factory()->fixed(5000)->create();

        $this->patchJson("/api/admin/promotions/{$promotion->id}", ['type' => 'percentage'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['value' => 'The value field is required when changing the type.']);

        $this->patchJson("/api/admin/promotions/{$promotion->id}", ['type' => 'percentage', 'value' => 15])
            ->assertOk()
            ->assertJsonPath('data.value', 15);
    });

    test('it validates a new value against the stored type', function () {
        $promotion = Promotion::factory()->percentage(20)->create();

        $this->patchJson("/api/admin/promotions/{$promotion->id}", ['value' => 150])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('value');
    });
});

describe('destroy', function () {
    test('it deletes the promotion while orders keep the code they used', function () {
        $promotion = Promotion::factory()->create(['code' => 'SUMMER20']);
        $order = Order::factory()->for($promotion)->create(['promotion_code' => 'SUMMER20']);
        $cart = Cart::factory()->for($promotion)->create();

        $this->deleteJson("/api/admin/promotions/{$promotion->id}")->assertNoContent();

        $this->assertModelMissing($promotion);
        expect($order->fresh())
            ->promotion_id->toBeNull()
            ->promotion_code->toBe('SUMMER20')
            ->and($cart->fresh()->promotion_id)->toBeNull();
    });
});
