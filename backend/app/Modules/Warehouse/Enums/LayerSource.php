<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Enums;

/**
 * FIFO qatlamining manbai — ENUMS.md §3, PROJECT.md 7.20.
 */
enum LayerSource: string
{
    /** Yetkazib beruvchidan kirim — `unit_cost` kirim narxidan. */
    case Purchase = 'purchase';

    /** Yo'ldan kelgan transfer — `unit_cost` sarflangan o'rtacha tannarx. */
    case TransferIn = 'transfer_in';

    /** Mijozdan qaytgan tovar — sotilgan tannarx bilan. */
    case Return = 'return';

    /** Inventarizatsiya ortiqchasi yoki qo'lda tuzatish. */
    case Adjustment = 'adjustment';

    /** Tizimga o'tishdagi boshlang'ich qoldiq. */
    case Initial = 'initial';

    /** Dublikat tovardan ko'chirilgan qatlam (7.17). */
    case Merge = 'merge';
}
