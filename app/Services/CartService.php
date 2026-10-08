<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Cart mutations. Each one locks the customer's cart row so concurrent requests for the same
 * customer (double clicks, parallel tabs, a running checkout) are applied one at a time.
 *
 * Stock is checked here but not reserved: checkout re-validates it under row locks.
 */
class CartService
{
    /**
     * The customer's cart for display. Read-only: a customer without a cart gets an unsaved empty one.
     */
    public function currentCart(User $user): Cart
    {
        $cart = $user->cart()->first();

        if ($cart === null) {
            return $user->cart()->make()->setRelation('items', new EloquentCollection);
        }

        return $this->withDetails($cart);
    }

    /**
     * Load everything the cart response needs in a fixed number of queries.
     */
    public function withDetails(Cart $cart): Cart
    {
        return $cart->load(['items' => fn ($query) => $query->orderBy('id')->with('product')]);
    }

    /**
     * Add a product, merging with an existing line for the same product.
     */
    public function addItem(User $user, Product $product, int $quantity): Cart
    {
        $cart = DB::transaction(function () use ($user, $product, $quantity): Cart {
            $user->cart()->firstOrCreate();
            $cart = $user->cart()->lockForUpdate()->firstOrFail();
            $item = $cart->items()->where('product_id', $product->id)->first();
            $newQuantity = ($item?->quantity ?? 0) + $quantity;

            $this->ensureAvailable($product, $newQuantity);

            $item
                ? $item->update(['quantity' => $newQuantity])
                : $cart->items()->create(['product_id' => $product->id, 'quantity' => $newQuantity]);

            return $cart;
        });

        return $this->withDetails($cart);
    }

    public function updateItem(User $user, string $itemId, int $quantity): Cart
    {
        $cart = DB::transaction(function () use ($user, $itemId, $quantity): Cart {
            $cart = $this->lockExistingCartFor($user, $itemId);
            $item = $this->findItem($cart, $itemId);

            $this->ensureAvailable($item->product, $quantity);

            $item->update(['quantity' => $quantity]);

            return $cart;
        });

        return $this->withDetails($cart);
    }

    public function removeItem(User $user, string $itemId): Cart
    {
        $cart = DB::transaction(function () use ($user, $itemId): Cart {
            $cart = $this->lockExistingCartFor($user, $itemId);
            $this->findItem($cart, $itemId)->delete();

            return $cart;
        });

        return $this->withDetails($cart);
    }

    /**
     * A customer without a cart cannot own the requested item, so that is reported as a missing item.
     */
    private function lockExistingCartFor(User $user, string $itemId): Cart
    {
        return $user->cart()->lockForUpdate()->first()
            ?? throw (new ModelNotFoundException)->setModel(CartItem::class, [$itemId]);
    }

    /**
     * Scoped to the customer's own cart, so another customer's item id is a 404.
     */
    private function findItem(Cart $cart, string $itemId): CartItem
    {
        return $cart->items()->with('product')->findOrFail($itemId);
    }

    /**
     * @throws InsufficientStockException
     */
    private function ensureAvailable(Product $product, int $quantity): void
    {
        if ($quantity > $product->availableQuantity()) {
            throw InsufficientStockException::forProduct($product, $quantity);
        }
    }
}
