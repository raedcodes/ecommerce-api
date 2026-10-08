<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;

/**
 * An expected business-rule failure that is returned to the API client
 * as `{ "message": ..., "code": ..., "details"?: ... }`.
 *
 * These are normal outcomes (e.g. insufficient stock), so they are never reported to the logs.
 */
abstract class ApiException extends Exception implements ShouldntReport
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(array_filter([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            'details' => $this->details,
        ]), $this->status);
    }
}
