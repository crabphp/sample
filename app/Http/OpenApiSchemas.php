<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Response shapes for actions that return database rows as arrays. Keeping
 * them here lets the OpenAPI attributes name one schema instead of repeating
 * a long JSON Schema on every action.
 */
final class OpenApiSchemas
{
    public const TAG = [
        'type' => 'object',
        'properties' => ['id' => ['type' => 'integer'], 'name' => ['type' => 'string']],
        'required' => ['id', 'name'],
        'additionalProperties' => false,
    ];

    public const TAG_LIST = ['type' => 'array', 'items' => self::TAG];

    public const TAG_PAGE = [
        ...self::PRODUCT_PAGE,
        'properties' => [...self::PRODUCT_PAGE['properties'], 'data' => self::TAG_LIST],
    ];

    public const TAG_SUMMARY = [
        'type' => 'object',
        'properties' => [
            'tag' => self::TAG,
            'products' => ['type' => 'integer'],
            'stock' => ['type' => 'integer'],
            'average_price' => ['type' => ['number', 'null'], 'description' => 'Average price in cents.'],
            'lowest_price' => ['type' => ['integer', 'null']],
            'highest_price' => ['type' => ['integer', 'null']],
            'orders' => ['type' => 'integer'],
            'order_total' => ['type' => 'integer', 'description' => 'Total in cents.'],
            'latest_order_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
            'latest_order_status' => [
                'type' => ['string', 'null'],
                'enum' => ['pending', 'paid', 'shipped', 'cancelled', null],
            ],
            'product_names' => ['type' => 'array', 'maxItems' => 20, 'items' => ['type' => 'string']],
            'order_statuses' => [
                'type' => 'array',
                'maxItems' => 20,
                'items' => self::ORDER_SUMMARY['properties']['status'],
            ],
        ],
        'required' => [
            'tag',
            'products',
            'stock',
            'average_price',
            'lowest_price',
            'highest_price',
            'orders',
            'order_total',
            'latest_order_at',
            'latest_order_status',
            'product_names',
            'order_statuses',
        ],
        'additionalProperties' => false,
    ];

    public const REVIEW = [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'user_id' => ['type' => 'integer'],
            'reviewable_type' => ['type' => 'string', 'enum' => ['products', 'orders']],
            'reviewable_id' => ['type' => 'integer'],
            'rating' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
            'body' => ['type' => 'string'],
            'created_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
            'updated_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
        ],
        'required' => [
            'id',
            'user_id',
            'reviewable_type',
            'reviewable_id',
            'rating',
            'body',
            'created_at',
            'updated_at',
        ],
        'additionalProperties' => false,
    ];

    public const REVIEW_PAGE = [
        ...self::PRODUCT_PAGE,
        'properties' => [
            ...self::PRODUCT_PAGE['properties'],
            'data' => ['type' => 'array', 'items' => self::REVIEW],
        ],
    ];

    public const REVIEW_SUBJECT = [
        'type' => 'object',
        'properties' => [
            'review' => self::REVIEW,
            'subject' => [
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['products', 'orders']],
                    'id' => ['type' => 'integer'],
                    'label' => ['type' => 'string'],
                ],
                'required' => ['type', 'id', 'label'],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['review', 'subject'],
        'additionalProperties' => false,
    ];

    public const DATABASE_TIME = [
        'type' => ['string', 'null'],
        'pattern' => '^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$',
        'description' => 'Database timestamp in the PHP default timezone, without an offset.',
    ];

    public const USER = [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => ['integer', 'string']],
            'name' => ['type' => 'string'],
            'email' => ['type' => 'string', 'format' => 'email'],
            'role' => ['type' => 'string', 'enum' => ['member', 'admin']],
        ],
        'required' => ['id', 'name', 'email', 'role'],
        'additionalProperties' => false,
    ];

    public const PRODUCT_SUMMARY = [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'sku' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'category' => ['type' => 'string'],
            'price' => ['type' => 'integer', 'description' => 'Price in cents.'],
            'stock' => ['type' => 'integer', 'minimum' => 0],
        ],
        'required' => ['id', 'sku', 'name', 'category', 'price', 'stock'],
        'additionalProperties' => false,
    ];

    public const PRODUCT = [
        'type' => 'object',
        'properties' => [
            ...self::PRODUCT_SUMMARY['properties'],
            'details' => ['type' => ['string', 'null'], 'description' => 'JSON text as stored in the database.'],
            'created_at' => self::DATABASE_TIME,
            'updated_at' => self::DATABASE_TIME,
        ],
        'required' => [
            'id',
            'sku',
            'name',
            'category',
            'price',
            'stock',
            'details',
            'created_at',
            'updated_at',
        ],
        'additionalProperties' => false,
    ];

    public const PRODUCT_PAGE = [
        'type' => 'object',
        'properties' => [
            'data' => ['type' => 'array', 'items' => self::PRODUCT_SUMMARY],
            'current_page' => ['type' => 'integer', 'minimum' => 1],
            'per_page' => ['type' => 'integer', 'minimum' => 1],
            'from' => ['type' => ['integer', 'null']],
            'to' => ['type' => ['integer', 'null']],
            'has_more' => ['type' => 'boolean'],
            'total' => ['type' => 'integer', 'minimum' => 0],
            'last_page' => ['type' => 'integer', 'minimum' => 1],
        ],
        'required' => ['data', 'current_page', 'per_page', 'from', 'to', 'has_more', 'total', 'last_page'],
        'additionalProperties' => false,
    ];

    public const CATEGORY = [
        'type' => 'object',
        'properties' => [
            'category' => ['type' => 'string'],
            'products' => ['type' => 'integer', 'minimum' => 0],
            'in_stock' => ['type' => 'integer', 'minimum' => 0],
        ],
        'required' => ['category', 'products', 'in_stock'],
        'additionalProperties' => false,
    ];

    public const ORDER_SUMMARY = [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'status' => ['type' => 'string', 'enum' => ['pending', 'paid', 'shipped', 'cancelled']],
            'total' => ['type' => 'integer', 'description' => 'Total in cents.'],
            'created_at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
            'items' => ['type' => 'integer', 'minimum' => 0],
        ],
        'required' => ['id', 'status', 'total', 'created_at', 'items'],
        'additionalProperties' => false,
    ];

    public const ORDER_LINE = [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'sku' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'quantity' => ['type' => 'integer', 'minimum' => 1],
            'price' => ['type' => 'integer', 'description' => 'Unit price in cents when ordered.'],
        ],
        'required' => ['id', 'sku', 'name', 'quantity', 'price'],
        'additionalProperties' => false,
    ];

    public const ORDER = [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'user_id' => ['type' => 'integer'],
            'status' => ['type' => 'string', 'enum' => ['pending', 'paid', 'shipped', 'cancelled']],
            'total' => ['type' => 'integer', 'description' => 'Total in cents.'],
            'version' => ['type' => 'integer'],
            'created_at' => self::DATABASE_TIME,
            'updated_at' => self::DATABASE_TIME,
            'follow_up_run' => ['type' => ['integer', 'null']],
            'lines' => ['type' => 'array', 'items' => self::ORDER_LINE],
        ],
        'required' => ['id', 'user_id', 'status', 'total', 'version', 'created_at', 'updated_at', 'lines'],
        'additionalProperties' => false,
    ];

    public const ORDER_CURSOR_PAGE = [
        'type' => 'object',
        'properties' => [
            'data' => ['type' => 'array', 'items' => self::ORDER_SUMMARY],
            'per_page' => ['type' => 'integer', 'minimum' => 1],
            'next_cursor' => ['type' => ['string', 'null']],
            'has_more' => ['type' => 'boolean'],
        ],
        'required' => ['data', 'per_page', 'next_cursor', 'has_more'],
        'additionalProperties' => false,
    ];

    public const AUTH_RESULT = [
        'type' => 'object',
        'properties' => [
            'user' => self::USER,
            'token' => ['type' => 'string'],
        ],
        'required' => ['user', 'token'],
        'additionalProperties' => false,
    ];

    public const CREATED_ORDER = [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'status' => ['type' => 'string', 'enum' => ['pending', 'paid', 'shipped', 'cancelled']],
            'total' => ['type' => 'integer', 'description' => 'Total in cents.'],
        ],
        'required' => ['id', 'status', 'total'],
        'additionalProperties' => false,
    ];

    public const SHIPPED_ORDER = [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'status' => ['type' => 'string', 'const' => 'shipped'],
        ],
        'required' => ['id', 'status'],
        'additionalProperties' => false,
    ];

    public const ASSISTANT_REPLY = [
        'type' => 'object',
        'properties' => [
            'answer' => ['type' => 'string'],
            'products' => ['type' => 'array', 'items' => ['type' => 'integer']],
            'turn' => ['type' => 'integer', 'minimum' => 1],
        ],
        'required' => ['answer', 'products', 'turn'],
        'additionalProperties' => false,
    ];

    public const READY = [
        'type' => 'object',
        'properties' => ['status' => ['type' => 'string', 'const' => 'ready']],
        'required' => ['status'],
        'additionalProperties' => false,
    ];

    public const HOME = [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'docs' => ['type' => 'string'],
        ],
        'required' => ['name', 'docs'],
        'additionalProperties' => false,
    ];

    public const TICKET = [
        'type' => 'object',
        'properties' => ['ticket' => ['type' => 'string']],
        'required' => ['ticket'],
        'additionalProperties' => false,
    ];
}
