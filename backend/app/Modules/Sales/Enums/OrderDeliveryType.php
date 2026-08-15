<?php

declare(strict_types=1);

namespace App\Modules\Sales\Enums;

/**
 * Buyurtma qanday yetadi — ENUMS.md §4.
 */
enum OrderDeliveryType: string
{
    /** Mijoz do'kondan olib ketadi. */
    case Pickup = 'pickup';

    /** Haydovchi yetkazadi (7.4). */
    case Courier = 'courier';
}
