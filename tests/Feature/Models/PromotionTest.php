<?php

use App\Models\Promotion;

test('codes are stored trimmed and upper-cased', function () {
    $promotion = Promotion::factory()->create(['code' => '  summer20 ']);

    expect($promotion->fresh()->code)->toBe('SUMMER20');
});
