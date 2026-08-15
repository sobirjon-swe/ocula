<?php

declare(strict_types=1);

namespace App\Modules\Sales\Enums;

/**
 * To'lov usuli — ENUMS.md §4.
 *
 * Eng muhim farq: **faqat naqd pul kassaga tushadi**. Karta terminal
 * hisobiga, o'tkazma bankka boradi — ularni kassa daftariga yozish
 * smena yopilishida soxta ortiqcha bergan bo'lardi.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Transfer = 'transfer';

    /** Onlayn — keyingi bosqich. */
    case Click = 'click';

    /** Onlayn — keyingi bosqich. */
    case Payme = 'payme';

    /**
     * Seyfdagi naqdga ta'sir qiladimi.
     */
    public function entersCashRegister(): bool
    {
        return $this === self::Cash;
    }
}
