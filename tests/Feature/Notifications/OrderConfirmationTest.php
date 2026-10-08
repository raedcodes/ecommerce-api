<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Notifications\OrderConfirmation;

test('the email lists each item, the discount and the total', function () {
    $order = Order::factory()->create(['subtotal' => 6000, 'discount_amount' => 1000, 'total' => 5000, 'promotion_code' => 'TAKE10']);
    OrderItem::factory()->for($order)->create(['product_name' => 'Desk Lamp', 'quantity' => 2, 'unit_price' => 3000, 'line_total' => 6000]);

    $mail = (new OrderConfirmation($order->load('items')))->toMail($order->user);

    expect($mail->subject)->toBe("Order #{$order->id} confirmed")
        ->and($mail->introLines)->toContain('2 × Desk Lamp — 60.00')
        ->toContain('Discount (TAKE10): -10.00')
        ->toContain('Total: 50.00');
});

test('the email escapes product names', function () {
    $order = Order::factory()->create();
    OrderItem::factory()->for($order)->create(['product_name' => "O'Reilly <script>alert('xss')</script>"]);

    $html = (string) (new OrderConfirmation($order->load('items')))->toMail($order->user)->render();

    expect($html)->toContain('&lt;script&gt;')
        ->not->toContain("<script>alert('xss')</script>");
});
