<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Enums;

/**
 * Onlayn navbat so'rovi holati — BOSQICH-11.md.
 */
enum AppointmentRequestStatus: string
{
    /** Hali ko'rib chiqilmagan. */
    case New = 'new';

    /** Xodim mijozga qo'ng'iroq qildi. */
    case Contacted = 'contacted';

    /** Real navbatga (`visits`) yozildi. */
    case Booked = 'booked';

    /** Mijoz bilan bog'lanib bo'lmadi yoki voz kechdi. */
    case Declined = 'declined';
}
