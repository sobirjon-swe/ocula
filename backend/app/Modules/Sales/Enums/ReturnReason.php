<?php

declare(strict_types=1);

namespace App\Modules\Sales\Enums;

/**
 * Qaytarish sababi — ENUMS.md §4.
 *
 * Sabab hisobot uchun kerak: `product_defect` va `wrong_prescription`
 * ko'payib ketsa, muammo mijozda emas — yetkazib beruvchida yoki
 * shifokorda.
 */
enum ReturnReason: string
{
    case CustomerChangedMind = 'customer_changed_mind';
    case WrongPrescription = 'wrong_prescription';
    case ProductDefect = 'product_defect';
    case WrongItem = 'wrong_item';
    case Other = 'other';
}
