<?php

namespace App\Http\Controllers\Api;

use App\Actions\CancelOrder;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Support\Facades\Gate;

class CancelOrderController extends Controller
{
    /**
     * Responds 200 with the cancelled order, including when it was already cancelled.
     */
    public function __invoke(Order $order, CancelOrder $cancelOrder): OrderResource
    {
        Gate::authorize('cancel', $order);

        return new OrderResource($cancelOrder->handle($order));
    }
}
