<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/*
 * Response and request body schemas. Money fields are decimal strings in dollars.
 */
#[OA\Schema(
    schema: 'Error',
    required: ['message', 'code'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: "Only 3 units of 'Desk Lamp' are available."),
        new OA\Property(property: 'code', description: 'Machine-readable error code.', type: 'string', example: 'insufficient_stock'),
        new OA\Property(property: 'details', description: 'Extra context for some errors.', type: 'object', nullable: true, additionalProperties: true),
    ],
)]
#[OA\Schema(
    schema: 'ValidationError',
    required: ['message', 'code', 'errors'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The quantity field must be at least 1.'),
        new OA\Property(property: 'code', type: 'string', example: 'validation_failed'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            example: ['quantity' => ['The quantity field must be at least 1.']],
            additionalProperties: new OA\AdditionalProperties(type: 'array', items: new OA\Items(type: 'string')),
        ),
    ],
)]
#[OA\Schema(
    schema: 'PaginationLinks',
    properties: [
        new OA\Property(property: 'first', type: 'string', nullable: true),
        new OA\Property(property: 'last', type: 'string', nullable: true),
        new OA\Property(property: 'prev', type: 'string', nullable: true),
        new OA\Property(property: 'next', type: 'string', nullable: true),
    ],
)]
#[OA\Schema(
    schema: 'PaginationMeta',
    properties: [
        new OA\Property(property: 'current_page', type: 'integer', example: 1),
        new OA\Property(property: 'from', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'last_page', type: 'integer', example: 4),
        new OA\Property(property: 'path', type: 'string'),
        new OA\Property(property: 'per_page', type: 'integer', example: 15),
        new OA\Property(property: 'to', type: 'integer', nullable: true, example: 15),
        new OA\Property(property: 'total', type: 'integer', example: 50),
    ],
)]
#[OA\Schema(
    schema: 'User',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Jane Customer'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jane@example.com'),
        new OA\Property(property: 'is_admin', type: 'boolean', example: false),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'AuthToken',
    properties: [
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'user', ref: '#/components/schemas/User'),
            new OA\Property(property: 'token', type: 'string', example: '1|Xy7...'),
            new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
        ], type: 'object'),
    ],
)]
#[OA\Schema(
    schema: 'Product',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Walnut Desk Lamp'),
        new OA\Property(property: 'sku', type: 'string', example: 'LAMP-001'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'price', type: 'string', example: '49.99'),
        new OA\Property(property: 'stock_quantity', type: 'integer', example: 5),
        new OA\Property(property: 'in_stock', type: 'boolean', example: true),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive']),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'CartItem',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 7),
        new OA\Property(property: 'product_id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Walnut Desk Lamp'),
        new OA\Property(property: 'sku', type: 'string', example: 'LAMP-001'),
        new OA\Property(property: 'unit_price', description: 'Current product price.', type: 'string', example: '49.99'),
        new OA\Property(property: 'quantity', type: 'integer', example: 2),
        new OA\Property(property: 'line_total', type: 'string', example: '99.98'),
        new OA\Property(property: 'available_quantity', description: 'Units that can be bought now (0 if the product was deactivated).', type: 'integer', example: 5),
        new OA\Property(property: 'is_available', description: 'False when stock dropped below the quantity or the product was deactivated.', type: 'boolean'),
    ],
)]
#[OA\Schema(
    schema: 'Cart',
    properties: [
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/CartItem')),
        new OA\Property(property: 'item_count', type: 'integer', example: 3),
        new OA\Property(property: 'subtotal', type: 'string', example: '154.00'),
        new OA\Property(
            property: 'promotion',
            description: 'The applied code, re-evaluated on every read. An invalid code is reported and not discounted.',
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'SUMMER20'),
                new OA\Property(property: 'type', type: 'string', enum: ['percentage', 'fixed']),
                new OA\Property(property: 'is_valid', type: 'boolean'),
                new OA\Property(property: 'error', properties: [
                    new OA\Property(property: 'code', type: 'string', example: 'coupon_minimum_not_met'),
                    new OA\Property(property: 'message', type: 'string'),
                ], type: 'object', nullable: true),
            ],
            type: 'object',
            nullable: true,
        ),
        new OA\Property(property: 'discount', type: 'string', example: '30.80'),
        new OA\Property(property: 'total', type: 'string', example: '123.20'),
    ],
)]
#[OA\Schema(
    schema: 'OrderItem',
    description: 'Snapshot taken at purchase time; later product changes do not affect it.',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'product_id', description: 'Null if the product was later deleted.', type: 'integer', nullable: true),
        new OA\Property(property: 'name', type: 'string', example: 'Walnut Desk Lamp'),
        new OA\Property(property: 'sku', type: 'string', example: 'LAMP-001'),
        new OA\Property(property: 'unit_price', type: 'string', example: '49.99'),
        new OA\Property(property: 'quantity', type: 'integer', example: 2),
        new OA\Property(property: 'line_total', type: 'string', example: '99.98'),
    ],
)]
#[OA\Schema(
    schema: 'Order',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 42),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'processing', 'shipped', 'delivered', 'cancelled']),
        new OA\Property(property: 'subtotal', type: 'string', example: '154.00'),
        new OA\Property(property: 'discount', type: 'string', example: '30.80'),
        new OA\Property(property: 'total', type: 'string', example: '123.20'),
        new OA\Property(property: 'promotion_code', type: 'string', nullable: true, example: 'SUMMER20'),
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/OrderItem')),
        new OA\Property(property: 'cancelled_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'Promotion',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'code', type: 'string', example: 'SUMMER20'),
        new OA\Property(property: 'type', type: 'string', enum: ['percentage', 'fixed']),
        new OA\Property(
            property: 'value',
            description: 'Whole percent (integer) for percentage codes; dollar amount (string) for fixed codes.',
            oneOf: [new OA\Schema(type: 'integer', example: 20), new OA\Schema(type: 'string', example: '10.00')],
        ),
        new OA\Property(property: 'min_cart_amount', type: 'string', nullable: true, example: '100.00'),
        new OA\Property(property: 'max_discount_amount', type: 'string', nullable: true, example: '50.00'),
        new OA\Property(property: 'starts_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'ends_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'usage_limit', type: 'integer', nullable: true, example: 1000),
        new OA\Property(property: 'per_customer_limit', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'times_used', type: 'integer', example: 0),
        new OA\Property(property: 'is_active', type: 'boolean'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'ProductInput',
    description: 'All fields required on create (POST); any subset on update (PATCH/PUT).',
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Walnut Desk Lamp'),
        new OA\Property(property: 'sku', description: 'Unique; letters, numbers, dots, dashes, underscores.', type: 'string', maxLength: 64, example: 'LAMP-001'),
        new OA\Property(property: 'description', type: 'string', maxLength: 5000, nullable: true),
        new OA\Property(property: 'price', description: 'Dollars, up to 2 decimals.', type: 'string', example: '49.99'),
        new OA\Property(property: 'stock_quantity', type: 'integer', minimum: 0, example: 5),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'inactive']),
    ],
)]
#[OA\Schema(
    schema: 'PromotionInput',
    description: 'code, type and value are required on create (POST); any subset on update (PATCH/PUT). Changing `type` requires a new `value`.',
    properties: [
        new OA\Property(property: 'code', description: 'Stored upper-case; unique ignoring case.', type: 'string', maxLength: 50, example: 'SUMMER20'),
        new OA\Property(property: 'type', type: 'string', enum: ['percentage', 'fixed']),
        new OA\Property(property: 'value', description: 'Whole percent 1–100, or a dollar amount for fixed codes.', type: 'string', example: '20'),
        new OA\Property(property: 'min_cart_amount', type: 'string', nullable: true, example: '100.00'),
        new OA\Property(property: 'max_discount_amount', type: 'string', nullable: true, example: '50.00'),
        new OA\Property(property: 'starts_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'ends_at', description: 'Must be after starts_at.', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'usage_limit', type: 'integer', minimum: 1, nullable: true),
        new OA\Property(property: 'per_customer_limit', type: 'integer', minimum: 1, nullable: true),
        new OA\Property(property: 'is_active', type: 'boolean'),
    ],
)]
final class Schemas {}
