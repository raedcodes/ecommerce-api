<?php

use App\Support\Money;

test('cents are formatted as a two-decimal string', function (int $cents, string $expected) {
    expect(Money::format($cents))->toBe($expected);
})->with([
    'zero' => [0, '0.00'],
    'single cent' => [5, '0.05'],
    'cents and dollars' => [14999, '149.99'],
    'whole dollars' => [100000, '1000.00'],
]);

test('decimal amounts are converted to cents without float drift', function (string $amount, int $expected) {
    expect(Money::toCents($amount))->toBe($expected);
})->with([
    'whole number' => ['10', 1000],
    'one decimal' => ['10.5', 1050],
    'value that is inexact as a float' => ['0.29', 29],
    'large amount' => ['99999.99', 9999999],
]);
