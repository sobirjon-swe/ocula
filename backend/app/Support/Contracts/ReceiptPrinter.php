<?php

declare(strict_types=1);

namespace App\Support\Contracts;

/**
 * Chek chiqarish — PROJECT.md 7.16, §15 #10.
 *
 * Fiskal onlayn-kassa **hozircha yo'q**, chek faqat ichki hujjat.
 * Lekin arxitektura kelajakka tayyor: chek chiqarish shu interfeys
 * ortida turadi. Fiskal apparat kerak bo'lganda faqat implementatsiya
 * almashtiriladi — `Sales` moduli o'zgarmaydi.
 *
 * Bog'lash `AppServiceProvider::register()` da:
 * `$this->app->bind(ReceiptPrinter::class, PdfReceiptPrinter::class);`
 */
interface ReceiptPrinter
{
    /**
     * Chekni chiqaradi va natija manzilini (fayl yo'li, URL yoki
     * fiskal apparat javobi) qaytaradi.
     *
     * @param  array<string, mixed>  $receipt  Chek ma'lumotlari
     */
    public function print(array $receipt): string;

    /**
     * Fiskal rejimda ishlayaptimi (hisobotda ajratish uchun).
     */
    public function isFiscal(): bool;
}
