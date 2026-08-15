<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Services;

use App\Modules\Sales\Models\Customer;
use App\Support\Text\Transliterator;
use Illuminate\Support\Facades\Lang;

/**
 * Mijoz tilida bot xabari — PROJECT.md §10.
 *
 * Bu kod so'rov tsiklidan tashqarida ishlaydi (navbat, konsol buyrug'i),
 * shuning uchun `SetLocaleFromRequest` yo'q — til to'g'ridan-to'g'ri
 * `customers.locale` dan olinadi. `uz-cyrl` uchun alohida tarjima fayli
 * yo'q (§10) — `uz-latn` xabari `Transliterator` orqali o'giriladi.
 */
final class CustomerMessage
{
    /**
     * @param  array<string, mixed>  $replace
     */
    public static function for(Customer $customer, string $key, array $replace = []): string
    {
        $locale = $customer->locale;

        $text = Lang::get($key, $replace, $locale->sourceLocale()->value);

        return $locale->isDerived() ? Transliterator::toCyrillic($text) : $text;
    }
}
