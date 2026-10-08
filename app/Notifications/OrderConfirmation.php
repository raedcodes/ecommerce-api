<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmation extends Notification
{
    use Queueable;

    public function __construct(public Order $order) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Order #{$this->order->id} confirmed")
            ->line("Thanks for your order #{$this->order->id}.");

        foreach ($this->order->items as $item) {
            $message->line("{$item->quantity} × {$item->product_name} — ".Money::format($item->line_total));
        }

        if ($this->order->discount_amount > 0) {
            $message->line("Discount ({$this->order->promotion_code}): -".Money::format($this->order->discount_amount));
        }

        return $message->line('Total: '.Money::format($this->order->total));
    }
}
