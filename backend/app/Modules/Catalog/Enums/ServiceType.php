<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

/**
 * Xizmat turi — ENUMS.md §2.
 */
enum ServiceType: string
{
    /** Shifokor ko'rigi. */
    case Exam = 'exam';

    /** Linzani ramkaga o'rnatish (ustaxona). */
    case Assembly = 'assembly';

    /** Ta'mir va sozlash. */
    case Repair = 'repair';
}
