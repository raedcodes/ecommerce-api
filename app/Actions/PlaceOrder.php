<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Events\OrderPlaced;
use App\Exceptions\CouponException;
use App\Exceptions\EmptyCartException;
use App\Exceptions\InsufficientStockException;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use App\Services\ProductCatalog;
use App\Services\PromotionEvaluator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns the customer's cart into an order.
 *
 * Everything runs in one transaction. Rows are locked in a fixed order — cart, then products by
 * ascending id, then the promotion — so concurrent checkouts queue behind each other instead of
 * overselling stock or a coupon, and lock cycles (deadlocks) cannot form between them. Prices and
 * totals come only from the locked rows; nothing the client sends is trusted.
 */
class PlaceOrder
{
    public function __construct(private PromotionEvaluator $promotions) {}

    /**
     * Retrying with the same idempotency key returns the order the first attempt created. The key is
     * stored in the same transaction as the order, so a retry sees either the committed order or nothing.
     *
     * @throws EmptyCartException
     * @throws InsufficientStockException
     * @throws CouponException
     */
    public function handle(User $customer, ?string $idempotencyKey = null): Order
    {
        return DB::transaction(function () use ($customer, $idempotencyKey): Order {
            // Serialises checkouts and cart edits for this customer.
            $cart = $customer->cart()->lockForUpdate()->first();

            if ($idempotencyKey !== null) {
                $existing = $customer->orders()->where('idempotency_key', $idempotencyKey)->first();

                if ($existing !== null) {
                    return $existing->load('items');
                }
            }

            /** @var Collection<int, CartItem> $items */
            $items = $cart?->items()->orderBy('product_id')->get() ?? new Collection;

            if ($items->isEmpty()) {
                throw EmptyCartException::make();
            }

            /** @var Collection<int, Product> $products */
            $products = Product::query()
                ->whereKey($items->pluck('product_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $this->ensureAvailable($items, $products);

            $lines = $items->map(fn (CartItem $item): array => [
                'product_id' => $item->product_id,
                'product_name' => $products[$item->product_id]->name,
                'product_sku' => $products[$item->product_id]->sku,
                'unit_price' => $products[$item->product_id]->price,
                'quantity' => $item->quantity,
                'line_total' => $products[$item->product_id]->price * $item->quantity,
            ])->all();

            $subtotal = array_sum(array_column($lines, 'line_total'));

            $promotion = $cart->promotion_id === null
                ? null
                : Promotion::query()->whereKey($cart->promotion_id)->lockForUpdate()->first();

            $discount = $promotion === null ? 0 : $this->promotions->discountFor($promotion, $customer, $subtotal);

            foreach ($items as $item) {
                $this->decrementStock($products[$item->product_id], $item->quantity);
            }

            $order = $customer->orders()->create([
                'status' => OrderStatus::Pending,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'total' => $subtotal - $discount,
                'promotion_id' => $promotion?->id,
                'promotion_code' => $promotion?->code,
                'idempotency_key' => $idempotencyKey,
            ]);

            $order->items()->createMany($lines);

            $promotion?->increment('times_used');

            $cart->items()->delete();
            $cart->promotion()->dissociate()->save();

            // Both take effect only after the transaction commits.
            ProductCatalog::invalidate();
            OrderPlaced::dispatch($order);

            return $order->load('items');
        }, attempts: 3);
    }

    /**
     * Checks every line against the locked rows and reports all shortages at once.
     *
     * @param  Collection<int, CartItem>  $items
     * @param  Collection<int, Product>  $products
     *
     * @throws InsufficientStockException
     */
    private function ensureAvailable(Collection $items, Collection $products): void
    {
        $shortages = $items
            ->filter(fn (CartItem $item): bool => $item->quantity > $products[$item->product_id]->availableQuantity())
            ->map(fn (CartItem $item): array => [
                'product_id' => $item->product_id,
                'name' => $products[$item->product_id]->name,
                'requested' => $item->quantity,
                'available' => $products[$item->product_id]->availableQuantity(),
            ])
            ->values()
            ->all();

        if ($shortages !== []) {
            throw InsufficientStockException::forItems($shortages);
        }
    }

    /**
     * The row is already locked and checked; the conditional UPDATE is a second guard, and the
     * unsigned column a third, so stock can never go negative.
     *
     * @throws InsufficientStockException
     */
    private function decrementStock(Product $product, int $quantity): void
    {
        $updated = Product::query()
            ->whereKey($product->id)
            ->where('stock_quantity', '>=', $quantity)
            ->decrement('stock_quantity', $quantity);

        if ($updated !== 1) {
            throw InsufficientStockException::forProduct($product, $quantity);
        }
    }
}
