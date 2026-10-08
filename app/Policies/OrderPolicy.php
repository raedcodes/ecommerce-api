<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderPolicy
{
    /**
     * Customers may only see their own orders.
     */
    public function view(User $user, Order $order): Response
    {
        return $this->owns($user, $order);
    }

    private function owns(User $user, Order $order): Response
    {
        return $order->user_id === $user->id
            ? Response::allow()
            : Response::deny('You do not have access to this order.');
    }
}
