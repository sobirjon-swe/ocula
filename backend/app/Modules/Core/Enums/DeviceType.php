<?php

declare(strict_types=1);

namespace App\Modules\Core\Enums;

/**
 * Qurilma turi — ENUMS.md §1, PROJECT.md 7.14.
 *
 * Shifokor va usta umumiy planshetda ishlaydi: qurilma bir marta
 * ro'yxatdan o'tadi, xodim 4 xonali PIN bilan almashadi.
 */
enum DeviceType: string
{
    case Tablet = 'tablet';
    case Phone = 'phone';
    case Pos = 'pos';
}
