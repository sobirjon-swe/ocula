<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Text\Transliterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `Transliterator` — PROJECT.md 7.13, §10, ANALIZ 3.14.
 *
 * Eng muhim shart: kirill va lotin yozuvidagi bir xil tovar **bir xil**
 * `search_key` berishi kerak, aks holda sotuvchi tovarni topolmaydi va
 * katalogda dublikat paydo bo'ladi.
 */
final class TransliteratorTest extends TestCase
{
    #[Test]
    #[DataProvider('sameProductCases')]
    public function it_maps_cyrillic_and_latin_spelling_to_the_same_key(string $cyrillic, string $latin): void
    {
        $this->assertSame(
            Transliterator::searchKey($cyrillic),
            Transliterator::searchKey($latin),
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function sameProductCases(): array
    {
        return [
            'Ray Ban' => ['Рэй Бан', 'Rey Ban'],
            'tire bilan' => ['Рэй-Бан', 'Rey Ban'],
            'katta harf' => ['РЭЙ БАН', 'rey ban'],
        ];
    }

    #[Test]
    public function it_strips_punctuation_and_case(): void
    {
        $this->assertSame('rayban3025', Transliterator::searchKey('Ray-Ban 3025'));
        $this->assertSame('rayban3025', Transliterator::searchKey('  RAY BAN  3025 '));
        $this->assertSame('rb3025qora', Transliterator::searchKey('RB 3025 qora'));
    }

    #[Test]
    public function it_treats_all_apostrophe_variants_alike(): void
    {
        $expected = Transliterator::searchKey("ko'zoynak");

        $this->assertSame($expected, Transliterator::searchKey('koʻzoynak'));
        $this->assertSame($expected, Transliterator::searchKey('ko‘zoynak'));
        $this->assertSame($expected, Transliterator::searchKey('kozoynak'));
    }

    #[Test]
    public function it_converts_cyrillic_to_latin_preserving_case(): void
    {
        $this->assertSame('Rey', Transliterator::toLatin('Рэй'));
        $this->assertSame('choy', Transliterator::toLatin('чой'));
        $this->assertSame('Shisha', Transliterator::toLatin('Шиша'));
    }

    #[Test]
    public function it_converts_latin_to_cyrillic_for_the_uz_cyrl_interface(): void
    {
        // §10: uz-cyrl qo'lda tarjima qilinmaydi, shu yerdan hosil bo'ladi.
        $this->assertSame('чой', Transliterator::toCyrillic('choy'));
        $this->assertSame('шиша', Transliterator::toCyrillic('shisha'));
        $this->assertSame('ўзбек', Transliterator::toCyrillic("o'zbek"));
    }

    #[Test]
    public function it_returns_an_empty_key_for_punctuation_only_input(): void
    {
        $this->assertSame('', Transliterator::searchKey('--- ... ---'));
    }
}
