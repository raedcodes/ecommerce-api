<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Notifications\OrderConfirmation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Runs on the queue so sending mail never slows down or fails a checkout.
 */
class SendOrderConfirmation implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60];

    public function handle(OrderPlaced $event): void
    {
        $order = $event->order->loadMissing(['user', 'items']);

        $order->user->notify(new OrderConfirmation($order));
    }
}
