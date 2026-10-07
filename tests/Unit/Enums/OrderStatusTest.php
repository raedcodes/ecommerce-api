<?php

use App\Enums\OrderStatus;

test('only pending and processing orders are cancellable', function (OrderStatus $status, bool $expected) {
    expect($status->isCancellable())->toBe($expected);
})->with([
    'pending' => [OrderStatus::Pending, true],
    'processing' => [OrderStatus::Processing, true],
    'shipped' => [OrderStatus::Shipped, false],
    'delivered' => [OrderStatus::Delivered, false],
    'cancelled' => [OrderStatus::Cancelled, false],
]);
