<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Money\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `Money` — PROJECT.md §14, §15 #18–19.
 *
 * Bu testlar bazasiz ishlaydi (sof arifmetika), shuning uchun
 * Laravel TestCase emas, PHPUnit TestCase.
 */
final class MoneyTest extends TestCase
{
    #[Test]
    public function it_stores_two_decimal_places(): void
    {
        $this->assertSame('1250000.00', Money::of('1250000')->toString());
        $this->assertSame('0.00', Money::zero()->toString());
    }

    #[Test]
    public function it_adds_and_subtracts_without_float_drift(): void
    {
        // Float bilan 0.1 + 0.2 !== 0.3 bo'lardi.
        $sum = Money::of('0.10')->plus('0.20');

        $this->assertTrue($sum->equals('0.30'));
        $this->assertSame('0.30', $sum->toString());

        $this->assertSame('99.90', Money::of('100')->minus('0.10')->toString());
    }

    #[Test]
    public function it_multiplies_price_by_quantity(): void
    {
        $this->assertSame('375000.00', Money::of('125000')->multipliedBy(3)->toString());
    }

    #[Test]
    public function it_calculates_percentage_for_discounts(): void
    {
        $this->assertSame('12500.00', Money::of('100000')->percentage('12.5')->toString());
        $this->assertSame('0.00', Money::of('100000')->percentage(0)->toString());
    }

    /**
     * §15 #19 — yakuniy summa 100 so'mga yaxlitlanadi, "half up".
     */
    #[Test]
    #[DataProvider('roundingCases')]
    public function it_rounds_the_final_amount_to_the_configured_step(string $input, string $expected): void
    {
        $this->assertSame($expected, Money::of($input)->roundToStep(100)->toString());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function roundingCases(): array
    {
        return [
            'pastga' => ['1250049.00', '1250000.00'],
            'yuqoriga (yarmi)' => ['1250050.00', '1250100.00'],
            'yuqoriga' => ['1250099.00', '1250100.00'],
            'aniq qadam' => ['1250100.00', '1250100.00'],
            'nol' => ['0.00', '0.00'],
        ];
    }

    #[Test]
    public function it_reports_the_rounding_difference_for_the_order_row(): void
    {
        // 1 250 049 -> 1 250 000, farq -49 (`orders.rounding` ga yoziladi).
        $this->assertSame('-49.00', Money::of('1250049')->roundingDifference(100)->toString());
        $this->assertSame('50.00', Money::of('1250050')->roundingDifference(100)->toString());
    }

    #[Test]
    public function it_rounds_half_away_from_zero_for_negative_amounts(): void
    {
        $this->assertSame('-0.13', Money::of('-0.125')->toString());
    }

    #[Test]
    public function it_compares_amounts(): void
    {
        $this->assertTrue(Money::of('100')->greaterThan('99.99'));
        $this->assertTrue(Money::of('-1')->isNegative());
        $this->assertTrue(Money::zero()->isZero());
        $this->assertFalse(Money::of('0.00')->isPositive());
    }

    #[Test]
    public function it_parses_user_input_with_spaces_and_commas(): void
    {
        $this->assertSame('1250000.50', Money::parse('1 250 000,50')->toString());
    }

    #[Test]
    public function it_formats_for_display(): void
    {
        // PROJECT.md §10: `1 250 000 so'm`, ajratuvchi — uzilmas bo'shliq.
        $this->assertSame("1\u{00A0}250\u{00A0}000\u{00A0}so'm", Money::of('1250000')->format());
        $this->assertSame("1\u{00A0}250", Money::of('1250')->format(withCurrency: false));
        $this->assertSame('999', Money::of('999')->format(withCurrency: false));
    }

    #[Test]
    public function it_rejects_malformed_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of('1 250 000');
    }

    #[Test]
    public function it_rejects_amounts_that_do_not_fit_the_column(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // decimal(15,2) — 13 ta butun xona sig'adi, 14 tasi yo'q.
        Money::of('12345678901234');
    }

    #[Test]
    public function it_refuses_division_by_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::of('100')->dividedBy(0);
    }
}
