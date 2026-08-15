<?php

declare(strict_types=1);

namespace App\Support\Contracts;

use App\Modules\Core\Models\Shift;
use App\Modules\Core\Models\User;
use App\Support\Money\Money;

/**
 * Smena kassaga qanday tegishini bildiruvchi interfeys — PROJECT.md 7.21.
 *
 * Smena Core modulida, kassa daftari esa Finance modulida yashaydi.
 * `OpenShift`/`CloseShift` bevosita `CashRegister` ga bog'lansa, Core
 * Finance'ga qaram bo'lib qolardi — shuning uchun oradagi shartnoma shu
 * yerda, Support qatlamida turadi. Implementatsiya —
 * `App\Modules\Finance\Services\CashRegister`, binding
 * `FinanceServiceProvider::register()` da.
 *
 * Interfeys ataylab tor: unda faqat **smena** uchun kerak bo'lgan uchta
 * yozuv va bitta hisob bor. Sotuv, qaytarish, xarajat yozuvlari
 * `CashRegister` ning o'zidan chaqiriladi (Sales moduli `StockLedger` ni
 * qanday chaqirsa, shunday).
 */
interface CashLedger
{
    /**
     * Smena bo'yicha kutilgan naqd — PROJECT.md 5.2.
     *
     *     opening_cash + Σ(ishorali harakatlar, `shift_opening` dan tashqari)
     *
     * `shift_opening` chiqarib tashlanadi: u `opening_cash` ning
     * daftardagi ko'rinishi, ikkalasini qo'shsak boshlang'ich naqd ikki
     * marta sanalardi.
     */
    public function expectedCash(Shift $shift): Money;

    /**
     * Smena boshidagi naqd daftarga tushadi (musbat bo'lsa).
     */
    public function recordShiftOpening(User $author, Shift $shift, Money $amount): void;

    /**
     * Kamomad — sanoqdan keyin daftar seyfdagi haqiqiy pul bilan
     * moslashishi uchun (`out`).
     */
    public function recordShortage(User $author, Shift $shift, Money $amount): void;

    /**
     * Ortiqcha — kamomadning teskarisi (`in`).
     */
    public function recordSurplus(User $author, Shift $shift, Money $amount): void;
}
