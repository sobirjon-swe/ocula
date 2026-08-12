<?php

declare(strict_types=1);

namespace App\Support\Text;

/**
 * Kirill ↔ lotin transliteratsiyasi — PROJECT.md §10, ANALIZ 3.14.
 *
 * **Yagona manba.** Ikki joyda ishlatiladi:
 *
 * 1. `products.search_key` — fuzzy qidiruv (7.13). "Рэй Бан 3025" ham,
 *    "Ray-Ban 3025" ham bir xil kalitga tushishi kerak.
 * 2. `uz-cyrl` interfeysi — `uz-latn` dan avtomatik hosila (§10).
 *    Ya'ni 3 ta tarjima yoziladi, 4-tasi shu klassdan chiqadi.
 *
 * Frontendda ham xuddi shu jadval bo'lishi shart, aks holda qidiruv
 * natijalari ikki tomonda farq qiladi.
 */
final class Transliterator
{
    /**
     * Kirill → lotin. Ko'p harfli kalitlar birinchi bo'lib tekshiriladi
     * (`replacePreservingCase()` eng uzun mosligini oladi).
     *
     * @var array<string, string>
     */
    private const array CYRILLIC_TO_LATIN = [
        'ё' => 'yo', 'ж' => 'j', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sh',
        'ю' => 'yu', 'я' => 'ya', 'ц' => 'ts', 'ў' => "o'", 'ғ' => "g'",
        'қ' => 'q', 'ҳ' => 'h', 'ъ' => "'", 'ь' => '', 'э' => 'e',
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
        'е' => 'e', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k',
        'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p',
        'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f',
        'х' => 'x', 'ы' => 'i',
    ];

    /**
     * Lotin → kirill. Digraflar (ch, sh, yo…) birinchi bo'lib almashadi.
     *
     * @var array<string, string>
     */
    private const array LATIN_TO_CYRILLIC = [
        "o'" => 'ў', "g'" => 'ғ', 'oʻ' => 'ў', 'gʻ' => 'ғ',
        'ch' => 'ч', 'sh' => 'ш', 'yo' => 'ё', 'yu' => 'ю', 'ya' => 'я',
        'ts' => 'ц',
        'a' => 'а', 'b' => 'б', 'd' => 'д', 'e' => 'е', 'f' => 'ф',
        'g' => 'г', 'h' => 'ҳ', 'i' => 'и', 'j' => 'ж', 'k' => 'к',
        'l' => 'л', 'm' => 'м', 'n' => 'н', 'o' => 'о', 'p' => 'п',
        'q' => 'қ', 'r' => 'р', 's' => 'с', 't' => 'т', 'u' => 'у',
        'v' => 'в', 'x' => 'х', 'y' => 'й', 'z' => 'з',
        'c' => 'к', 'w' => 'в',
    ];

    /**
     * Kirill matnni lotinga o'giradi. Bosh harflar saqlanadi: "Рэй" → "Rey".
     */
    public static function toLatin(string $text): string
    {
        return self::replacePreservingCase($text, self::CYRILLIC_TO_LATIN);
    }

    /**
     * Lotin matnni kirillga o'giradi (`uz-cyrl` interfeysi uchun — §10).
     */
    public static function toCyrillic(string $text): string
    {
        return self::replacePreservingCase($text, self::LATIN_TO_CYRILLIC);
    }

    /**
     * `products.search_key` ni yasaydi — SCHEMA.md §2.
     *
     * Bosqichlar: kichik harf → kirilldan lotinga → apostroflarni olib
     * tashlash → harf va raqamdan boshqa hamma narsani olib tashlash.
     *
     *     "Рэй Бан 3025"   → "reyban3025"
     *     "Ray-Ban 3025"   → "rayban3025"
     *     "RB 3025 qora"   → "rb3025qora"
     */
    public static function searchKey(string $text): string
    {
        $latin = self::toLatin(mb_strtolower(trim($text), 'UTF-8'));

        // Apostrofning barcha ko'rinishlari bir xil natija bersin.
        $latin = str_replace(["'", 'ʻ', '‘', '’', '`'], '', $latin);

        return preg_replace('/[^a-z0-9]+/u', '', $latin) ?? '';
    }

    /**
     * @param  array<string, string>  $map
     */
    private static function replacePreservingCase(string $text, array $map): string
    {
        $maxKeyLength = 0;
        foreach (array_keys($map) as $key) {
            $maxKeyLength = max($maxKeyLength, mb_strlen($key, 'UTF-8'));
        }

        $result = '';
        $length = mb_strlen($text, 'UTF-8');
        $position = 0;

        while ($position < $length) {
            $matched = false;

            for ($take = $maxKeyLength; $take >= 1; $take--) {
                $chunk = mb_substr($text, $position, $take, 'UTF-8');
                $lowerChunk = mb_strtolower($chunk, 'UTF-8');

                if (! isset($map[$lowerChunk])) {
                    continue;
                }

                $replacement = $map[$lowerChunk];

                // Asl bo'lak bosh harfdan boshlangan bo'lsa — natija ham shunday.
                if ($replacement !== '' && self::startsUppercase($chunk)) {
                    $replacement = mb_strtoupper(mb_substr($replacement, 0, 1, 'UTF-8'), 'UTF-8')
                        .mb_substr($replacement, 1, null, 'UTF-8');
                }

                $result .= $replacement;
                $position += $take;
                $matched = true;
                break;
            }

            if (! $matched) {
                $result .= mb_substr($text, $position, 1, 'UTF-8');
                $position++;
            }
        }

        return $result;
    }

    private static function startsUppercase(string $chunk): bool
    {
        $first = mb_substr($chunk, 0, 1, 'UTF-8');

        return $first !== mb_strtolower($first, 'UTF-8');
    }
}
