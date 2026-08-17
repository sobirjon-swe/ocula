<?php

declare(strict_types=1);

namespace App\Modules\Finance\Enums;

/**
 * Yetkazib beruvchi tranzaksiyasi turi — PROJECT.md 7.15, BOSQICH-10.md §10a.
 *
 * `amount` ustuni ishorali (SCHEMA.md): `purchase` doim manfiy yoziladi
 * (biz qarzdor bo'lamiz), `payment` doim musbat (qarzimiz kamayadi).
 * `correction` — qo'lda kiritilgan ishora, `manual()` ro'yxatida faqat
 * `payment`/`correction` bor: `purchase` faqat `ReceivePurchase` dan
 * avtomatik tug'iladi.
 */
enum SupplierTxType: string
{
    case Purchase = 'purchase';
    case Payment = 'payment';
    case Correction = 'correction';

    /**
     * @return array<int, self>
     */
    public static function manual(): array
    {
        return [self::Payment, self::Correction];
    }
}
