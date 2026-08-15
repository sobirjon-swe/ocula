<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Modules\Core\Actions\Shift\OpenShift;
use App\Modules\Core\Enums\Role;
use App\Modules\Core\Enums\ShiftStatus;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Shift;
use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\CashCategory;
use App\Modules\Finance\Enums\CashDirection;
use App\Modules\Finance\Models\CashMovement;
use App\Modules\Finance\Services\CashRegister;
use App\Support\Exceptions\ImmutableRecordException;
use App\Support\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Kassa daftari — PROJECT.md 5.2, 7.8-A, 7.21.
 *
 * Asosiy kafolatlar: kutilgan naqd daftardan hisoblanadi va
 * boshlang'ich pul ikki marta sanalmaydi; smena yopilishida farq
 * kamomad/ortiqcha bo'lib daftarga tushadi; yozuv tahrirlanmaydi.
 */
final class CashRegisterTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    private Branch $branch;

    private User $actor;

    private CashRegister $register;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->branch = Branch::factory()->create(['code' => 'K']);
        $this->actor = User::factory()->for($this->branch)->create();
        $this->register = app(CashRegister::class);
    }

    /**
     * `shift_opening` yig'indidan chiqarib tashlanadi — u
     * `opening_cash` ning daftardagi ko'rinishi. Aks holda smena
     * boshidagi pul ikki marta sanalardi.
     */
    #[Test]
    public function the_opening_cash_is_never_counted_twice(): void
    {
        $shift = $this->openShift('200000');

        $this->assertSame('200000.00', $this->register->expectedCash($shift)->toString());
        $this->assertSame(1, CashMovement::query()->withoutGlobalScopes()->count());
    }

    #[Test]
    public function income_and_outgo_move_the_expected_cash(): void
    {
        $shift = $this->openShift('100000');

        $this->register->record(
            $this->actor, $this->branch->id, CashCategory::Collection, Money::of('300000'), $shift,
        );
        $this->register->record(
            $this->actor, $this->branch->id, CashCategory::Expense, Money::of('50000'), $shift,
        );

        // 100 000 + 300 000 − 50 000
        $this->assertSame('350000.00', $this->register->expectedCash($shift)->toString());
    }

    /**
     * Factory bilan yasalgan (harakatsiz) smenada kutilgan naqd
     * boshlang'ich naqdga teng bo'lib qolishi kerak — mavjud
     * `ShiftApiTest` shunga tayanadi.
     */
    #[Test]
    public function a_shift_without_movements_expects_only_its_opening_cash(): void
    {
        $shift = Shift::factory()->for($this->branch)->create(['opening_cash' => '75000.00']);

        $this->assertSame('75000.00', $this->register->expectedCash($shift)->toString());
    }

    #[Test]
    public function closing_a_shift_with_a_shortage_writes_it_to_the_cash_book(): void
    {
        $seller = $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postAction('/api/v1/shifts', [
            'branch_id' => $this->branch->id,
            'opening_cash' => '200000',
        ])->assertCreated();

        $shift = Shift::query()->firstOrFail();

        $this->postAction("/api/v1/shifts/{$shift->id}/close", ['actual_cash' => '185000'])
            ->assertOk()
            ->assertJsonPath('data.expected_cash', '200000.00')
            ->assertJsonPath('data.difference', '-15000.00');

        $shortage = CashMovement::query()
            ->withoutGlobalScopes()
            ->where('category', CashCategory::Shortage->value)
            ->firstOrFail();

        $this->assertSame(CashDirection::Out, $shortage->type);
        $this->assertSame('15000.00', $shortage->amount->toString());
        $this->assertSame($seller->id, $shortage->created_by);

        // Sanoqdan keyin daftar seyfdagi haqiqiy pul bilan mos keladi.
        $this->assertSame('185000.00', $this->register->expectedCash($shift->fresh())->toString());
    }

    #[Test]
    public function a_surplus_is_written_the_other_way_round(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postAction('/api/v1/shifts', [
            'branch_id' => $this->branch->id,
            'opening_cash' => '100000',
        ])->assertCreated();

        $shift = Shift::query()->firstOrFail();

        $this->postAction("/api/v1/shifts/{$shift->id}/close", ['actual_cash' => '120000'])->assertOk();

        $surplus = CashMovement::query()
            ->withoutGlobalScopes()
            ->where('category', CashCategory::Surplus->value)
            ->firstOrFail();

        $this->assertSame(CashDirection::In, $surplus->type);
        $this->assertSame('20000.00', $surplus->amount->toString());
    }

    #[Test]
    public function an_exact_count_writes_no_correction_at_all(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postAction('/api/v1/shifts', [
            'branch_id' => $this->branch->id,
            'opening_cash' => '100000',
        ])->assertCreated();

        $shift = Shift::query()->firstOrFail();
        $this->postAction("/api/v1/shifts/{$shift->id}/close", ['actual_cash' => '100000'])->assertOk();

        $this->assertSame(ShiftStatus::Closed, $shift->fresh()?->status);
        $this->assertSame(1, CashMovement::query()->withoutGlobalScopes()->count());
    }

    /**
     * Insert-only (7.21): tuzatish faqat storno orqali.
     */
    #[Test]
    public function a_cash_movement_can_not_be_updated(): void
    {
        $movement = $this->recordExpense();

        $this->expectException(ImmutableRecordException::class);
        $movement->update(['amount' => '1.00']);
    }

    #[Test]
    public function a_cash_movement_can_not_be_deleted(): void
    {
        $movement = $this->recordExpense();

        $this->expectException(ImmutableRecordException::class);
        $movement->delete();
    }

    #[Test]
    public function a_reversal_cancels_the_entry_without_deleting_it(): void
    {
        $shift = $this->openShift('0');

        $movement = $this->register->record(
            $this->actor, $this->branch->id, CashCategory::Expense, Money::of('40000'), $shift,
        );

        $this->assertSame('-40000.00', $this->register->expectedCash($shift)->toString());

        $reversal = $this->register->reverse($this->actor, $movement, 'Xato yozildi');

        $this->assertSame(CashDirection::In, $reversal->type);
        $this->assertSame(CashCategory::Correction, $reversal->category);
        $this->assertSame($movement->id, $reversal->reverses_id);
        $this->assertSame('0.00', $this->register->expectedCash($shift)->toString());

        // Asl yozuv o'z joyida qoldi.
        $this->assertNotNull(CashMovement::query()->withoutGlobalScopes()->find($movement->id));
    }

    #[Test]
    public function a_reversal_can_not_be_reversed_again(): void
    {
        $shift = $this->openShift('0');

        $movement = $this->register->record(
            $this->actor, $this->branch->id, CashCategory::Expense, Money::of('40000'), $shift,
        );

        $reversal = $this->register->reverse($this->actor, $movement, 'Xato');

        $this->expectException(ValidationException::class);
        $this->register->reverse($this->actor, $reversal, 'Yana xato');
    }

    /**
     * Boshlang'ich naqd smenaning o'z ustunida turadi — uni daftardan
     * tuzatish ikkala raqamni bir-biridan ajratib yuborardi.
     */
    #[Test]
    public function the_opening_entry_can_not_be_reversed(): void
    {
        $this->openShift('100000');

        $opening = CashMovement::query()
            ->withoutGlobalScopes()
            ->where('category', CashCategory::ShiftOpening->value)
            ->firstOrFail();

        $this->expectException(ValidationException::class);
        $this->register->reverse($this->actor, $opening, 'Xato');
    }

    #[Test]
    public function a_seller_sees_the_cash_book_but_may_not_write_into_it(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->getJson('/api/v1/cash/movements')->assertOk();

        $this->postAction('/api/v1/cash/movements', [
            'branch_id' => $this->branch->id,
            'category' => 'expense',
            'amount' => '50000',
        ])->assertForbidden();
    }

    #[Test]
    public function an_accountant_may_record_a_manual_entry(): void
    {
        $this->actingAsEmployee(Role::Accountant, $this->branch);

        $this->postAction('/api/v1/cash/movements', [
            'branch_id' => $this->branch->id,
            'category' => 'deposit_to_safe',
            'amount' => '500000',
            'description' => 'Inkassatsiya',
        ])->assertCreated()
            ->assertJsonPath('data.type', CashDirection::Out->value)
            ->assertJsonPath('data.signed_amount', '-500000.00');
    }

    /**
     * Sotuv va qaytarish yozuvlari o'z amalidan tug'iladi — ularni
     * qo'lda yozib bo'lmaydi, aks holda daftar hujjatdan ajralib
     * ketardi.
     */
    #[Test]
    public function an_automatic_category_can_not_be_written_by_hand(): void
    {
        $this->actingAsEmployee(Role::Accountant, $this->branch);

        $this->postAction('/api/v1/cash/movements', [
            'branch_id' => $this->branch->id,
            'category' => 'sale',
            'amount' => '50000',
        ])->assertStatus(422)->assertJsonValidationErrors('category');
    }

    #[Test]
    public function the_summary_reports_the_current_shift(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->postAction('/api/v1/shifts', [
            'branch_id' => $this->branch->id,
            'opening_cash' => '150000',
        ])->assertCreated();

        $this->getJson('/api/v1/cash/summary')
            ->assertOk()
            ->assertJsonPath('data.opening_cash', '150000.00')
            ->assertJsonPath('data.expected_cash', '150000.00');
    }

    #[Test]
    public function the_summary_is_null_when_no_shift_is_open(): void
    {
        $this->actingAsEmployee(Role::Seller, $this->branch);

        $this->getJson('/api/v1/cash/summary')->assertOk()->assertJsonPath('data', null);
    }

    /**
     * Smenani `OpenShift` orqali ochamiz — shunda `shift_opening`
     * yozuvi ham daftarga tushadi.
     */
    private function recordExpense(): CashMovement
    {
        return $this->register->record(
            $this->actor,
            $this->branch->id,
            CashCategory::Expense,
            Money::of('10000'),
            $this->openShift('50000'),
        );
    }

    private function openShift(string $openingCash): Shift
    {
        return app(OpenShift::class)
            ->handle($this->actor, $this->branch->id, Money::of($openingCash));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<JsonResponse>
     */
    private function postAction(string $uri, array $payload = []): TestResponse
    {
        return $this->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson($uri, $payload);
    }
}
