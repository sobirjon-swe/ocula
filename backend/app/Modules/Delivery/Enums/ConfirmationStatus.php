<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Enums;

/**
 * Mijozning yetkazish tasdig'i — ENUMS.md §7, PROJECT.md 7.4.
 *
 * Haydovchi tasdig'idan **mustaqil** o'q (BOSQICH-8.md §4): haydovchi
 * "yetkazdim" desa ham, mijoz "yo'q" deb javob berishi mumkin.
 */
enum ConfirmationStatus: string
{
    /** Mijozga savol yuborildi, javob kutilmoqda. */
    case Awaiting = 'awaiting';

    /** Mijoz "Ha" dedi. */
    case Confirmed = 'confirmed';

    /** Mijoz "Yo'q" dedi — direktorga signal. */
    case Disputed = 'disputed';

    /** 24 soat javob yo'q — avto-yopildi. */
    case Unconfirmed = 'unconfirmed';
}
