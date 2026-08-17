<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Enums;

/**
 * Yo'l varaqasi holati — ENUMS.md §7.
 *
 *     planned → in_progress → finished
 *     planned → cancelled
 */
enum TripStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Finished = 'finished';
    case Cancelled = 'cancelled';

    public function canBeStarted(): bool
    {
        return $this === self::Planned;
    }

    public function canBeFinished(): bool
    {
        return $this === self::InProgress;
    }

    public function canBeCancelled(): bool
    {
        return $this === self::Planned;
    }
}
