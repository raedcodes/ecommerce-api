<?php

namespace App\Services;

use App\Exceptions\CouponException;
use App\Exceptions\EmptyCartException;
use App\Exceptions\InsufficientStockException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Cart mutations. Each one locks the customer's cart row so concurrent requests for the same
 * customer (double clicks, parallel tabs, a running checkout) are applied one at a time.
 *
 * Stock and promotions are checked here but not reserved: checkout re-validates both under row locks.
 */
class CartService
{
    public function __construct(private PromotionEvaluator $promotions) {}

    /**
     * The customer's cart for display. Read-only: a customer without a cart gets an unsaved empty one.
     */
    public function currentCart(User $user): CartSummary
    {
        $cart = $user->cart()->first()
            ?? $user->cart()->make()->setRelation('items', new EloquentCollection)->setRelation('promotion', null);

        return $this->summarize($cart, $user);
    }

    /**
     * Add a product, merging with an existing line for the same product.
     */
    public function addItem(User $user, Product $product, int $quantity): CartSummary
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

        return $this->summarize($cart, $user);
    }

    public function updateItem(User $user, string $itemId, int $quantity): CartSummary
    {
        $cart = DB::transaction(function () use ($user, $itemId, $quantity): Cart {
            $cart = $this->lockExistingCartFor($user) ?? throw $this->itemNotFound($itemId);
            $item = $this->findItem($cart, $itemId);

            $this->ensureAvailable($item->product, $quantity);

            $item->update(['quantity' => $quantity]);

            return $cart;
        });

        return $this->summarize($cart, $user);
    }

    public function removeItem(User $user, string $itemId): CartSummary
    {
        $cart = DB::transaction(function () use ($user, $itemId): Cart {
            $cart = $this->lockExistingCartFor($user) ?? throw $this->itemNotFound($itemId);
            $this->findItem($cart, $itemId)->delete();

            return $cart;
        });

        return $this->summarize($cart, $user);
    }

    /**
     * Attach a promotion after checking the customer is eligible for it with the current cart.
     *
     * @throws EmptyCartException
     * @throws CouponException
     */
    public function applyPromotion(User $user, string $code): CartSummary
    {
        $cart = DB::transaction(function () use ($user, $code): Cart {
            $cart = $this->lockExistingCartFor($user) ?? throw EmptyCartException::make();
            $cart->load('items.product');

            if ($cart->items->isEmpty()) {
                throw EmptyCartException::make();
            }

            $promotion = Promotion::findByCode($code) ?? throw CouponException::invalid();
            $this->promotions->ensureEligible($promotion, $user, $cart->subtotal());

            $cart->promotion()->associate($promotion)->save();

            return $cart;
        });

        return $this->summarize($cart, $user);
    }

    public function removePromotion(User $user): CartSummary
    {
        $cart = DB::transaction(function () use ($user): ?Cart {
            $cart = $this->lockExistingCartFor($user);
            $cart?->promotion()->dissociate()->save();

            return $cart;
        });

        return $cart ? $this->summarize($cart, $user) : $this->currentCart($user);
    }

    /**
     * Price the cart at current product prices and evaluate its promotion.
     * A promotion that is no longer valid is reported instead of failing the request.
     */
    private function summarize(Cart $cart, User $user): CartSummary
    {
        if ($cart->exists) {
            $cart->load(['items' => fn ($query) => $query->orderBy('id')->with('product'), 'promotion']);
        }

        $subtotal = $cart->subtotal();

        if ($cart->promotion === null) {
            return new CartSummary($cart, $subtotal, 0);
        }

        try {
            return new CartSummary($cart, $subtotal, $this->promotions->discountFor($cart->promotion, $user, $subtotal));
        } catch (CouponException $exception) {
            return new CartSummary($cart, $subtotal, 0, $exception);
        }
    }

    private function lockExistingCartFor(User $user): ?Cart
    {
        return $user->cart()->lockForUpdate()->first();
    }

    /**
     * A customer without a cart cannot own the requested item, so that is reported as a missing item.
     */
    private function itemNotFound(string $itemId): ModelNotFoundException
    {
        return (new ModelNotFoundException)->setModel(CartItem::class, [$itemId]);
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
