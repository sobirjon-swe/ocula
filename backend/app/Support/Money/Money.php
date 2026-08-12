<?php

declare(strict_types=1);

namespace App\Support\Money;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Pul qiymati — PROJECT.md §2, §15 #18.
 *
 * Bazada `decimal(15,2)`, PHP tomonda **string + bcmath**.
 * Float hech qachon ishlatilmaydi (§13 dagi taqiq).
 *
 * Ichki hisob-kitob `CALC_SCALE` (6 xona) aniqlikda ketadi, natija
 * `SCALE` (2 xona) ga **noldan uzoqqa** (half up) yaxlitlanadi. Mijozga
 * ko'rsatiladigan yakuniy summani `roundToStep()` bilan 100 so'mga
 * yaxlitlash — §15 #19.
 */
final class Money implements JsonSerializable, Stringable
{
    /** Saqlash aniqligi — `decimal(15,2)`. */
    public const int SCALE = 2;

    /** Oraliq hisob-kitob aniqligi (foiz, bo'lish). */
    public const int CALC_SCALE = 6;

    /** `decimal(15,2)` sig'imi: 13 ta butun xona. */
    private const int MAX_INTEGER_DIGITS = 13;

    /**
     * @param  numeric-string  $amount
     */
    private function __construct(private readonly string $amount) {}

    /**
     * Qiymatdan Money yasash.
     *
     * `float` ataylab qabul qilinmaydi — aniqlik yo'qoladi (§13).
     */
    public static function of(self|string|int $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return new self(self::round(self::normalize((string) $value)));
    }

    public static function zero(): self
    {
        return new self(self::round('0'));
    }

    /**
     * Foydalanuvchi kiritgan matndan yasash: "1 250 000,50" → "1250000.50".
     */
    public static function parse(string $input): self
    {
        $cleaned = preg_replace('/[\s\x{00A0}\x{202F}]+/u', '', $input) ?? '';

        return self::of(str_replace(',', '.', $cleaned));
    }

    public function plus(self|string|int $other): self
    {
        return new self(self::round(
            bcadd($this->amount, self::of($other)->amount, self::CALC_SCALE)
        ));
    }

    public function minus(self|string|int $other): self
    {
        return new self(self::round(
            bcsub($this->amount, self::of($other)->amount, self::CALC_SCALE)
        ));
    }

    /**
     * Miqdorga ko'paytirish (masalan `price × quantity`).
     */
    public function multipliedBy(string|int $factor): self
    {
        return new self(self::round(
            bcmul($this->amount, self::normalize((string) $factor), self::CALC_SCALE)
        ));
    }

    public function dividedBy(string|int $divisor): self
    {
        $normalized = self::normalize((string) $divisor);

        if (bccomp($normalized, '0', self::CALC_SCALE) === 0) {
            throw new InvalidArgumentException("Nolga bo'lib bo'lmaydi.");
        }

        return new self(self::round(
            bcdiv($this->amount, $normalized, self::CALC_SCALE)
        ));
    }

    /**
     * Summaning foizi: `100 000` ning `12.5%` → `12 500.00`.
     *
     * Chegirma hisoblashda ishlatiladi (7.12 — mukofot chegirmadan keyin).
     */
    public function percentage(string|int $percent): self
    {
        $product = bcmul($this->amount, self::normalize((string) $percent), self::CALC_SCALE);

        return new self(self::round(bcdiv($product, '100', self::CALC_SCALE)));
    }

    /**
     * Yakuniy summani qadamga yaxlitlash — §15 #19.
     *
     * `$step = 100` → `1 250 049` → `1 250 000`, `1 250 050` → `1 250 100`.
     * Farq `orders.rounding` qatoriga yoziladi.
     */
    public function roundToStep(?int $step = null): self
    {
        $step ??= (int) config('optika.money.rounding_step', 100);

        if ($step <= 1) {
            return new self(self::round(self::round($this->amount, 0)));
        }

        $stepAsString = self::normalize((string) $step);
        $quotient = bcdiv($this->amount, $stepAsString, self::CALC_SCALE);
        $roundedQuotient = self::round($quotient, 0);

        return new self(self::round(bcmul($roundedQuotient, $stepAsString, self::CALC_SCALE)));
    }

    /**
     * Yaxlitlash farqi: `roundToStep()` natijasi minus asl summa.
     *
     * Musbat = mijoz ko'proq to'laydi, manfiy = kamroq (`orders.rounding`).
     */
    public function roundingDifference(?int $step = null): self
    {
        return $this->roundToStep($step)->minus($this);
    }

    public function negated(): self
    {
        return new self(self::round(bcsub('0', $this->amount, self::CALC_SCALE)));
    }

    public function absolute(): self
    {
        return $this->isNegative() ? $this->negated() : $this;
    }

    public function compareTo(self|string|int $other): int
    {
        return bccomp($this->amount, self::of($other)->amount, self::SCALE);
    }

    public function equals(self|string|int $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    public function greaterThan(self|string|int $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function greaterThanOrEqual(self|string|int $other): bool
    {
        return $this->compareTo($other) >= 0;
    }

    public function lessThan(self|string|int $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function isZero(): bool
    {
        return bccomp($this->amount, '0', self::SCALE) === 0;
    }

    public function isPositive(): bool
    {
        return bccomp($this->amount, '0', self::SCALE) > 0;
    }

    public function isNegative(): bool
    {
        return bccomp($this->amount, '0', self::SCALE) < 0;
    }

    /**
     * Bazaga yoziladigan qiymat: `'1250000.00'`.
     *
     * @return numeric-string
     */
    public function toString(): string
    {
        return $this->amount;
    }

    /**
     * Tiyindagi butun qiymat (grafik va eksport uchun).
     */
    public function toMinorUnits(): int
    {
        return (int) bcmul($this->amount, '100', 0);
    }

    /**
     * Ekranga chiqadigan format — PROJECT.md §10: `1 250 000 so'm`.
     *
     * Ajratuvchi — uzilmas bo'shliq (summa satr o'rtasida bo'linib ketmasin).
     */
    public function format(bool $withCurrency = true, bool $withFraction = false): string
    {
        $negative = $this->isNegative();
        [$integer, $fraction] = explode('.', $this->absolute()->amount, 2);

        $result = ($negative ? '−' : '').self::groupDigits($integer);

        if ($withFraction && $fraction !== '00') {
            $result .= ','.$fraction;
        }

        return $withCurrency ? $result."\u{00A0}so'm" : $result;
    }

    /**
     * @return numeric-string
     */
    public function jsonSerialize(): string
    {
        return $this->amount;
    }

    public function __toString(): string
    {
        return $this->amount;
    }

    /**
     * Raqamlarni uchtalab ajratadi: `1250000` → `1 250 000`.
     *
     * Ajratuvchi uzilmas bo'shliq (ko'p baytli), shuning uchun satrni
     * teskari aylantirib bo'lmaydi — o'ngdan chapga sanaymiz.
     */
    private static function groupDigits(string $digits): string
    {
        $length = strlen($digits);
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            if ($i > 0 && ($length - $i) % 3 === 0) {
                $result .= "\u{00A0}";
            }

            $result .= $digits[$i];
        }

        return $result;
    }

    /**
     * Kiruvchi qiymatni bcmath tushunadigan holatga keltirish va tekshirish.
     *
     * Ikki bosqich ataylab: regex eksponensial ("1e5") va o'n oltilik
     * yozuvni rad etadi, `is_numeric()` esa bcmath bilan mosligini
     * kafolatlaydi.
     *
     * @return numeric-string
     */
    private static function normalize(string $value): string
    {
        $value = trim($value);

        if ($value === '' || $value === '-') {
            return '0';
        }

        $value = ltrim($value, '+');

        if (preg_match('/^-?\d+(\.\d+)?$/', $value) !== 1 || ! is_numeric($value)) {
            throw new InvalidArgumentException(
                "Noto'g'ri pul qiymati: '{$value}'. Kutilgan format: '1250000.50'."
            );
        }

        return $value;
    }

    /**
     * bcmath kesib tashlaydi — bu yerda "half up" (noldan uzoqqa) yaxlitlanadi.
     *
     * @param  numeric-string  $value
     * @return numeric-string
     */
    private static function round(string $value, int $scale = self::SCALE): string
    {
        // Oxirgi saqlanadigan xonaning yarmi: 5 / 10^(scale+1).
        // Masalan scale=2 → '0.005', scale=0 → '0.5'.
        $half = bcdiv('5', bcpow('10', (string) ($scale + 1)), $scale + 1);

        $rounded = bccomp($value, '0', self::CALC_SCALE) < 0
            ? bcsub($value, $half, $scale)
            : bcadd($value, $half, $scale);

        // "-0.00" o'rniga "0.00" qaytsin.
        if (bccomp($rounded, '0', $scale) === 0) {
            $rounded = bcadd('0', '0', $scale);
        }

        self::assertFits($rounded);

        return $rounded;
    }

    /**
     * `decimal(15,2)` sig'imidan oshib ketmasin — DB xatosi o'rniga aniq xabar.
     *
     * @param  numeric-string  $value
     */
    private static function assertFits(string $value): void
    {
        $integerPart = ltrim(explode('.', ltrim($value, '-'), 2)[0], '0');

        if (strlen($integerPart) > self::MAX_INTEGER_DIGITS) {
            throw new InvalidArgumentException(
                "Pul qiymati juda katta: '{$value}'. Maksimum — decimal(15,2)."
            );
        }
    }
}
