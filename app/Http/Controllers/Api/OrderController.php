<?php

namespace App\Http\Controllers\Api;

use App\Actions\CancelOrder;
use App\Actions\PlaceOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    /**
     * The customer's own orders, newest first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $orders = $request->user()->orders()
            ->with('items')
            ->latest()
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return OrderResource::collection($orders);
    }

    /**
     * Place an order from the customer's cart.
     *
     * Responds 201 for a new order and 200 when an Idempotency-Key replays an existing one.
     */
    public function checkout(CheckoutRequest $request, PlaceOrder $placeOrder): OrderResource
    {
        $order = $placeOrder->handle(
            $request->user(),
            $request->validated('idempotency_key'),
        );

        return new OrderResource($order);
    }

    public function show(Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        return new OrderResource($order->load('items'));
    }

    /**
     * Responds 200 with the cancelled order, including when it was already cancelled.
     */
    public function cancel(Order $order, CancelOrder $cancelOrder): OrderResource
    {
        Gate::authorize('cancel', $order);

        return new OrderResource($cancelOrder->handle($order));
    }
}
