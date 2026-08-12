<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| OPTIKA — biznes sozlamalari
|--------------------------------------------------------------------------
|
| Bu yerdagi qiymatlar **standart** (default) hisoblanadi. Direktor
| o'zgartira oladigan sozlamalar `settings` jadvalida yashaydi
| (SCHEMA.md §1) va shu fayldagi qiymatni ustidan yopadi.
|
| Manba: docs/PROJECT.md §15, docs/SCHEMA.md §1.
|
*/

return [

    'version' => env('OPTIKA_VERSION', '0.1.0-bosqich-0'),

    /*
    |--------------------------------------------------------------------------
    | Pul — §15 #18, #19
    |--------------------------------------------------------------------------
    |
    | Hisob-kitob to'liq aniqlikda (`decimal(15,2)` + bcmath), mijozga
    | ko'rsatiladigan YAKUNIY summa `rounding_step` ga yaxlitlanadi
    | (round half up), farq `orders.rounding` qatoriga yoziladi.
    |
    */
    'money' => [
        'rounding_step' => (int) env('OPTIKA_MONEY_ROUNDING_STEP', 100),
        'currency' => 'UZS',
    ],

    /*
    |--------------------------------------------------------------------------
    | Buyurtma — 7.7
    |--------------------------------------------------------------------------
    |
    | Avans MAJBURIY EMAS (biznes qarori). Mexanizm tayyor turadi:
    | direktor olib ketilmagan buyurtmalar zararini bir necha oy ko'rgach,
    | foizni o'zi qo'yishi mumkin.
    |
    */
    'orders' => [
        'min_prepayment_percent' => (int) env('OPTIKA_MIN_PREPAYMENT_PERCENT', 0),

        // Shuncha kundan keyin olinmagan buyurtma `abandoned_at` bilan belgilanadi.
        'abandoned_after_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retsept — 7.11
    |--------------------------------------------------------------------------
    */
    'prescriptions' => [
        'validity_months' => 12,

        // Yangi qiymat eskisidan shuncha diopterga farq qilsa — ogohlantirish (§10).
        'diopter_change_warning' => '1.50',
    ],

    /*
    |--------------------------------------------------------------------------
    | Qarz — 7.6
    |--------------------------------------------------------------------------
    |
    | Eslatma kunlari: muddatdan 1 kun oldin, muddat kuni, +3, +7.
    | 90+ kun = shubhali qarz, foyda hisobotidan chiqariladi.
    |
    */
    'debts' => [
        'reminder_days' => [-1, 0, 3, 7],
        'doubtful_after_days' => 90,
        'aging_buckets' => [7, 30, 90],
    ],

    /*
    |--------------------------------------------------------------------------
    | Yetkazish — 7.4
    |--------------------------------------------------------------------------
    */
    'delivery' => [
        // GPS mijoz manzilidan shuncha metrdan uzoq bo'lsa — belgilanadi
        // (ayblash uchun emas, hujjat uchun).
        'gps_tolerance_meters' => 500,

        // Mijoz javob bermasa, shuncha soatdan keyin `unconfirmed` bilan yopiladi.
        'auto_confirm_after_hours' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | Planshet PIN — 7.14
    |--------------------------------------------------------------------------
    */
    'devices' => [
        'pin_length' => 4,
        'idle_timeout_minutes' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Idempotentlik — §9, ANALIZ 3.7
    |--------------------------------------------------------------------------
    */
    'idempotency' => [
        'ttl_hours' => 24,
    ],

];
