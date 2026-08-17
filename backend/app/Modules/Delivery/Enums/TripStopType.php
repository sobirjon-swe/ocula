<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Enums;

/**
 * To'xtash turi — ENUMS.md §7.
 */
enum TripStopType: string
{
    /** Filialga transfer yetkazish. */
    case Branch = 'branch';

    /** Mijozga buyurtma yetkazish. */
    case Customer = 'customer';
}
