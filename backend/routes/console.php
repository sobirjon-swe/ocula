<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Telegram bildirishnomalari — BOSQICH-7.md §7. Loyihadagi birinchi
// scheduler yozuvlari. Ertalab: do'kon ochilishidan oldin mijozlar
// bilan bog'lanish uchun vaqt qolsin.
Schedule::command('telegram:remind-debts')->dailyAt('08:00')->timezone('Asia/Tashkent')->withoutOverlapping();
Schedule::command('telegram:remind-checkups')->dailyAt('08:15')->timezone('Asia/Tashkent')->withoutOverlapping();

// Yetkazish tasdig'i — BOSQICH-8.md §4. Har soat: 24 soatlik javob
// muddati bir kunlik buyruq bilan qo'pol hisoblanmasin.
Schedule::command('delivery:expire-confirmations')->hourly()->withoutOverlapping();

// Qarz holati — BOSQICH-10.md §10a. Qarz eslatmasidan oldin ishga
// tushadi: 08:00 dagi eslatma kuni bo'yicha yangilangan holatga tayansin.
Schedule::command('finance:refresh-debt-statuses')->dailyAt('07:45')->timezone('Asia/Tashkent')->withoutOverlapping();
Schedule::command('sales:mark-abandoned')->dailyAt('07:30')->timezone('Asia/Tashkent')->withoutOverlapping();
Schedule::command('catalog:report-duplicates')->weeklyOn(1, '08:30')->timezone('Asia/Tashkent')->withoutOverlapping();
Schedule::command('telegram:daily-summary')->everyFifteenMinutes()->between('22:00', '23:59')->timezone('Asia/Tashkent')->withoutOverlapping();
Schedule::command('telegram:daily-summary')->everyFifteenMinutes()->between('00:00', '02:45')->timezone('Asia/Tashkent')->withoutOverlapping();
