<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Enums;

/**
 * To'xtash holati — ENUMS.md §7.
 */
enum TripStopStatus: string
{
    /** Hali borilmadi. */
    case Pending = 'pending';

    /** Yetib bordi (GPS olinadi). */
    case Arrived = 'arrived';

    /** Topshirdi. */
    case Delivered = 'delivered';

    /** Topshira olmadi (mijoz yo'q, rad etdi). */
    case Failed = 'failed';

    /** O'tkazib yuborildi. */
    case Skipped = 'skipped';

    public function canBeDelivered(): bool
    {
        return $this === self::Pending || $this === self::Arrived;
    }

    public function canFail(): bool
    {
        return $this === self::Pending || $this === self::Arrived;
    }

    public function isResolved(): bool
    {
        return match ($this) {
            self::Delivered, self::Failed, self::Skipped => true,
            self::Pending, self::Arrived => false,
        };
    }
}
