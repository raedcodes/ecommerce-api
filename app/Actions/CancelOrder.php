<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderStatusException;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\ProductCatalog;
use Illuminate\Support\Facades\DB;

/**
 * Cancels an order and returns its stock and coupon use.
 *
 * The order row is locked and its status re-read inside the transaction, so a repeated or
 * concurrent request waits, then sees "cancelled" and changes nothing: stock is restored exactly
 * once. Products (ascending id) and the promotion are locked in the same order checkout uses.
 */
class CancelOrder
{
    /**
     * @throws InvalidOrderStatusException
     */
    public function handle(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->status === OrderStatus::Cancelled) {
                return $order->load('items');
            }

            if (! $order->status->isCancellable()) {
                throw InvalidOrderStatusException::cannotCancel($order->status);
            }

            // Items whose product was deleted have no stock to return.
            $items = $order->items()->whereNotNull('product_id')->orderBy('product_id')->get();

            Product::query()->whereKey($items->pluck('product_id'))->orderBy('id')->lockForUpdate()->get();

            foreach ($items as $item) {
                Product::query()->whereKey($item->product_id)->increment('stock_quantity', $item->quantity);
            }

            if ($order->promotion_id !== null) {
                Promotion::query()
                    ->whereKey($order->promotion_id)
                    ->where('times_used', '>', 0)
                    ->decrement('times_used');
            }

            $order->update(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);

            ProductCatalog::invalidate();

            return $order->load('items');
        }, attempts: 3);
    }
}
