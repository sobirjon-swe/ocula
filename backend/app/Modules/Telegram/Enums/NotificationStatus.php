<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Enums;

/**
 * Xabar yuborish natijasi — BOSQICH-7.md §4.
 */
enum NotificationStatus: string
{
    case Sent = 'sent';
    case Failed = 'failed';
}
