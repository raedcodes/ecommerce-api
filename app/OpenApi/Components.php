<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/*
 * Reusable error responses and query parameters.
 */
#[OA\Response(
    response: 'Unauthenticated',
    description: 'Missing or invalid bearer token (`unauthenticated`).',
    content: new OA\JsonContent(ref: '#/components/schemas/Error', example: ['message' => 'Unauthenticated.', 'code' => 'unauthenticated']),
)]
#[OA\Response(
    response: 'Forbidden',
    description: 'Authenticated but not allowed (`forbidden`), e.g. another customer\'s order or a non-admin on an admin route.',
    content: new OA\JsonContent(ref: '#/components/schemas/Error', example: ['message' => 'You do not have access to this order.', 'code' => 'forbidden']),
)]
#[OA\Response(
    response: 'NotFound',
    description: 'The resource does not exist (`product_not_found`, `order_not_found`, `cart_item_not_found`, `promotion_not_found`, `not_found`).',
    content: new OA\JsonContent(ref: '#/components/schemas/Error', example: ['message' => 'Product not found.', 'code' => 'product_not_found']),
)]
#[OA\Response(
    response: 'ValidationFailed',
    description: 'The input is invalid (`validation_failed`).',
    content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'),
)]
#[OA\Response(
    response: 'TooManyRequests',
    description: 'Rate limit exceeded (`too_many_requests`); see the `Retry-After` header.',
    headers: [new OA\Header(header: 'Retry-After', description: 'Seconds until another request is allowed.', schema: new OA\Schema(type: 'integer'))],
    content: new OA\JsonContent(ref: '#/components/schemas/Error', example: ['message' => 'Too Many Attempts.', 'code' => 'too_many_requests']),
)]
#[OA\Parameter(parameter: 'PerPage', name: 'per_page', description: 'Items per page (1–100).', in: 'query', schema: new OA\Schema(type: 'integer', default: 15, maximum: 100, minimum: 1))]
#[OA\Parameter(parameter: 'Page', name: 'page', description: 'Page number.', in: 'query', schema: new OA\Schema(type: 'integer', default: 1, minimum: 1))]
final class Components {}
