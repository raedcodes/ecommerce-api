<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('store', function () {
    test('it adds a product to the cart', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 1999, 'stock_quantity' => 5]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertCreated()
            ->assertJsonPath('data.items.0.product_id', $product->id)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.subtotal', '39.98');

        expect($user->cart->items()->sole())
            ->product_id->toBe($product->id)
            ->quantity->toBe(2);
    });

    test('it merges the quantity when the product is already in the cart', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 5]);
        CartItem::factory()->for(Cart::factory()->for($user))->for($product)->create(['quantity' => 1]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertCreated()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.quantity', 3);

        expect($user->cart->items()->sole()->quantity)->toBe(3);
    });

    test('it returns 422 insufficient_stock when the quantity exceeds the stock', function (int $stock, string $message) {
        $user = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Desk Lamp', 'stock_quantity' => $stock]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => $stock + 1])
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => $message,
                'code' => 'insufficient_stock',
                'details' => ['items' => [[
                    'product_id' => $product->id,
                    'name' => 'Desk Lamp',
                    'requested' => $stock + 1,
                    'available' => $stock,
                ]]],
            ]);

        expect(CartItem::count())->toBe(0);
    })->with([
        'out of stock' => [0, "'Desk Lamp' is out of stock."],
        'one unit left' => [1, "Only 1 unit of 'Desk Lamp' is available."],
        'several units left' => [3, "Only 3 units of 'Desk Lamp' are available."],
    ]);

    test('it returns 422 insufficient_stock when the merged quantity exceeds the stock', function () {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 3]);
        $item = CartItem::factory()->for(Cart::factory()->for($user))->for($product)->create(['quantity' => 2]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'insufficient_stock')
            ->assertJsonPath('details.items.0.requested', 4)
            ->assertJsonPath('details.items.0.available', 3);

        expect($item->fresh()->quantity)->toBe(2);
    });

    test('it returns 422 for invalid input', function (Closure $payload, array $errors) {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/cart/items', $payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errors);

        expect(CartItem::count())->toBe(0);
    })->with([
        'missing fields' => [fn () => [], ['product_id', 'quantity']],
        'zero quantity' => [fn () => ['product_id' => Product::factory()->create()->id, 'quantity' => 0], ['quantity']],
        'fractional quantity' => [fn () => ['product_id' => Product::factory()->create()->id, 'quantity' => 1.5], ['quantity']],
        'quantity above the column limit' => [fn () => ['product_id' => Product::factory()->create()->id, 'quantity' => CartItem::MAX_QUANTITY + 1], ['quantity']],
        'unknown product' => [fn () => ['product_id' => 999999, 'quantity' => 1], ['product_id' => 'The selected product does not exist or is not available.']],
        'inactive product' => [fn () => ['product_id' => Product::factory()->inactive()->create()->id, 'quantity' => 1], ['product_id' => 'The selected product does not exist or is not available.']],
    ]);
});

describe('update', function () {
    test('it changes the quantity', function () {
        $user = User::factory()->create();
        $item = CartItem::factory()
            ->for(Cart::factory()->for($user))
            ->for(Product::factory()->state(['price' => 500, 'stock_quantity' => 10]))
            ->create(['quantity' => 1]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/cart/items/{$item->id}", ['quantity' => 4])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 4)
            ->assertJsonPath('data.subtotal', '20.00');

        expect($item->fresh()->quantity)->toBe(4);
    });

    test('it accepts PUT as well as PATCH', function () {
        $user = User::factory()->create();
        $item = CartItem::factory()
            ->for(Cart::factory()->for($user))
            ->for(Product::factory()->state(['stock_quantity' => 10]))
            ->create(['quantity' => 1]);
        Sanctum::actingAs($user);

        $this->putJson("/api/cart/items/{$item->id}", ['quantity' => 3])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 3);
    });

    test('it returns 422 insufficient_stock when the quantity exceeds the stock', function () {
        $user = User::factory()->create();
        $item = CartItem::factory()
            ->for(Cart::factory()->for($user))
            ->for(Product::factory()->state(['stock_quantity' => 3]))
            ->create(['quantity' => 1]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/cart/items/{$item->id}", ['quantity' => 4])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'insufficient_stock')
            ->assertJsonPath('details.items.0.available', 3);

        expect($item->fresh()->quantity)->toBe(1);
    });

    test('it returns 422 when the quantity is below one', function () {
        $user = User::factory()->create();
        $item = CartItem::factory()->for(Cart::factory()->for($user))->create(['quantity' => 1]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/cart/items/{$item->id}", ['quantity' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');
    });

    test('it returns 404 for an item in another customer\'s cart', function () {
        $othersItem = CartItem::factory()->create(['quantity' => 1]);
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/cart/items/{$othersItem->id}", ['quantity' => 2])
            ->assertNotFound()
            ->assertExactJson(['message' => 'Cart item not found.', 'code' => 'cart_item_not_found']);

        expect($othersItem->fresh()->quantity)->toBe(1);
    });

    test('it returns 404 without creating a cart when the customer has none', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/cart/items/1', ['quantity' => 2])
            ->assertNotFound()
            ->assertJsonPath('code', 'cart_item_not_found');

        expect(Cart::count())->toBe(0);
    });
});

describe('destroy', function () {
    test('it removes the item from the cart', function () {
        $user = User::factory()->create();
        $item = CartItem::factory()->for(Cart::factory()->for($user))->create();
        Sanctum::actingAs($user);

        $this->deleteJson("/api/cart/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.subtotal', '0.00');

        $this->assertModelMissing($item);
    });

    test('it returns 404 for an item in another customer\'s cart', function () {
        $othersItem = CartItem::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson("/api/cart/items/{$othersItem->id}")
            ->assertNotFound()
            ->assertJsonPath('code', 'cart_item_not_found');

        $this->assertModelExists($othersItem);
    });
});
