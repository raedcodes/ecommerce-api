<?php

/**
 * Runs one checkout or cancellation in its own PHP process for the concurrency tests.
 *
 * Usage: php worker.php <checkout|cancel> <start-at-unix-microtime> <user-id|order-id>
 * Prints one JSON line: {"result":"ok"|"rejected"|"error", ...}.
 */

use App\Actions\CancelOrder;
use App\Actions\PlaceOrder;
use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Never run against anything but the dedicated test database.
if (! str_ends_with((string) config('database.connections.'.config('database.default').'.database'), '_testing')) {
    fwrite(STDERR, "Refusing to run outside the *_testing database.\n");
    exit(1);
}

[, $action, $startAt, $id] = $argv;

// Hold the window open right after the rows are read, so a missing lock would let another
// process read the same stale stock. With correct locking, the other process simply waits.
DB::listen(function (QueryExecuted $query): void {
    if (preg_match('/^select .* from `(products|orders)`/i', $query->sql)) {
        usleep(150_000);
    }
});

// Start every worker at the same moment, after the slow framework boot.
while (microtime(true) < (float) $startAt) {
    usleep(500);
}

try {
    $order = match ($action) {
        'checkout' => app(PlaceOrder::class)->handle(User::findOrFail($id)),
        'cancel' => app(CancelOrder::class)->handle(Order::findOrFail($id)),
    };

    echo json_encode(['result' => 'ok', 'order_id' => $order->id]).PHP_EOL;
} catch (ApiException $exception) {
    echo json_encode(['result' => 'rejected', 'code' => $exception->errorCode]).PHP_EOL;
} catch (Throwable $exception) {
    echo json_encode(['result' => 'error', 'message' => $exception->getMessage()]).PHP_EOL;
}
