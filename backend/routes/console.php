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
Schedule::command('telegram:remind-debts')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('telegram:remind-checkups')->dailyAt('08:15')->withoutOverlapping();
