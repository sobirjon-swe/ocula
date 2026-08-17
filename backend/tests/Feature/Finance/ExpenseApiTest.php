<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Finance\Enums\CashCategory;
use App\Modules\Finance\Models\CashMovement;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\ExpenseCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Xarajatlar — PROJECT.md §6.8, BOSQICH-10.md §10a.
 *
 * Asosiy kafolat: xarajat yozilganda kassadan avtomatik chiqim yoziladi
 * — ikkalasi ajralib qolmaydi.
 */
final class ExpenseApiTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    private Branch $branch;

    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();
        $this->branch = Branch::factory()->create(['code' => 'K']);
        $this->category = ExpenseCategory::factory()->create(['code' => 'rent']);
    }

    #[Test]
    public function an_accountant_writes_an_expense_and_the_cash_book_moves_with_it(): void
    {
        $this->actingAsEmployee(Role::Accountant, $this->branch);

        $this->postAction('/api/v1/expenses', [
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'amount' => '150000',
            'description' => 'Ijara',
        ])->assertCreated()
            ->assertJsonPath('data.amount', '150000.00')
            ->assertJsonPath('data.category.code', 'rent');

        $expense = Expense::query()->firstOrFail();

        $movement = CashMovement::query()->withoutGlobalScopes()
            ->where('source_type', Expense::class)
            ->where('source_id', $expense->id)
            ->firstOrFail();

        $this->assertSame(CashCategory::Expense, $movement->category);
        $this->assertSame('150000.00', $movement->amount->toString());
    }

    #[Test]
    public function a_doctor_may_not_write_an_expense(): void
    {
        $this->actingAsEmployee(Role::Doctor, $this->branch);

        $this->postAction('/api/v1/expenses', [
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'amount' => '50000',
        ])->assertForbidden();
    }

    #[Test]
    public function a_director_approves_an_expense(): void
    {
        $this->actingAsEmployee(Role::Accountant, $this->branch);
        $id = $this->postAction('/api/v1/expenses', [
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'amount' => '50000',
        ])->json('data.id');

        $director = $this->actingAsDirector();
        $this->postJson("/api/v1/expenses/{$id}/approve")
            ->assertOk()
            ->assertJsonPath('data.approved_by', $director->id);

        $this->postJson("/api/v1/expenses/{$id}/approve")->assertStatus(422);
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
