<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Enums;

/**
 * Vizit qayerdan kelib chiqqan — ENUMS.md §5.
 *
 * Manba hisobot uchun kerak: QR orqali keladiganlar ko'paysa, navbat
 * qog'ozi va sotuvchining vaqti tejaladi; `walk_in` ko'p bo'lsa,
 * oldindan yozilish ishlamayapti degani.
 */
enum VisitSource: string
{
    /** Mijoz filialdagi QR ni skanerlab navbat oldi. */
    case Qr = 'qr';

    /** Sotuvchi navbatga qo'shdi. */
    case Seller = 'seller';

    /** Shunchaki kirib kelgan mijoz. */
    case WalkIn = 'walk_in';
}
