<?php

declare(strict_types=1);

namespace App;

/**
 * Where an order stands, as the orders.status column stores it.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';
}
