<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Modules\Core\Models\Branch;
use App\Support\Documents\DocumentNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Hujjat raqami — PROJECT.md §14, ANALIZ 3.12.
 *
 * Kafolat: parallel generatsiyada raqam **takrorlanmaydi**.
 */
final class DocumentNumberTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_formats_the_number_as_branch_period_counter(): void
    {
        $this->assertSame('A-2608-00147', DocumentNumber::format('A', '2608', 147));
        $this->assertSame('B-2601-00001', DocumentNumber::format('b', '2601', 1));
    }

    #[Test]
    public function it_increments_per_branch_type_and_period(): void
    {
        $branch = Branch::factory()->main()->create(['code' => 'A']);
        $at = now()->setDate(2026, 8, 11);

        $first = DB::transaction(fn (): string => DocumentNumber::next('order', $branch->id, 'A', $at));
        $second = DB::transaction(fn (): string => DocumentNumber::next('order', $branch->id, 'A', $at));

        $this->assertSame('A-2608-00001', $first);
        $this->assertSame('A-2608-00002', $second);
    }

    #[Test]
    public function it_keeps_separate_counters_per_document_type(): void
    {
        $branch = Branch::factory()->create(['code' => 'B']);
        $at = now()->setDate(2026, 8, 11);

        $order = DB::transaction(fn (): string => DocumentNumber::next('order', $branch->id, 'B', $at));
        $transfer = DB::transaction(fn (): string => DocumentNumber::next('transfer', $branch->id, 'B', $at));

        $this->assertSame('B-2608-00001', $order);
        $this->assertSame('B-2608-00001', $transfer);
        $this->assertDatabaseCount('document_sequences', 2);
    }

    #[Test]
    public function it_restarts_the_counter_in_a_new_month(): void
    {
        $branch = Branch::factory()->create(['code' => 'C']);

        $august = DB::transaction(
            fn (): string => DocumentNumber::next('order', $branch->id, 'C', now()->setDate(2026, 8, 11))
        );
        $september = DB::transaction(
            fn (): string => DocumentNumber::next('order', $branch->id, 'C', now()->setDate(2026, 9, 1))
        );

        $this->assertSame('C-2608-00001', $august);
        $this->assertSame('C-2609-00001', $september);
    }

    #[Test]
    public function it_never_repeats_a_number_across_many_calls(): void
    {
        $branch = Branch::factory()->create(['code' => 'D']);
        $at = now()->setDate(2026, 8, 11);

        $numbers = [];

        for ($i = 0; $i < 50; $i++) {
            $numbers[] = DB::transaction(
                fn (): string => DocumentNumber::next('order', $branch->id, 'D', $at)
            );
        }

        $this->assertCount(50, array_unique($numbers));
        $this->assertSame('D-2608-00050', end($numbers));
    }

    /*
     * `next()` tranzaksiyasiz chaqirilsa `RuntimeException` otadi, lekin
     * buni shu yerda tekshirib bo'lmaydi: `RefreshDatabase` har bir testni
     * o'zi tranzaksiyaga o'raydi, ya'ni `transactionLevel()` doim > 0.
     * Himoya kodi `DocumentNumber::next()` da qoladi.
     */
}
