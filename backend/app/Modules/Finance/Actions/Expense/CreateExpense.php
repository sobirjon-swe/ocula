<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Expense;

use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\CashCategory;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\ExpenseCategory;
use App\Modules\Finance\Services\CashRegister;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Xarajat yozish — PROJECT.md §6.8, BOSQICH-10.md §10a.
 *
 * Kassadan chiqim **shu yerda**, xarajat bilan bir tranzaksiyada
 * yoziladi (`CashCategory::Expense`) — aks holda xarajat kassa
 * daftaridan ajralib qolardi.
 *
 * `$source` — avtomatik yaratilgan xarajatlar (taksi kabi) qaysi
 * hujjatdan kelib chiqqanini bildiradi; qo'lda yozilganda `null`.
 */
final class CreateExpense
{
    public function __construct(private readonly CashRegister $cash) {}

    public function handle(
        User $author,
        int $branchId,
        ExpenseCategory $category,
        Money $amount,
        ?CarbonImmutable $date = null,
        ?string $description = null,
        ?Model $source = null,
        ?string $receiptPath = null,
    ): Expense {
        return DB::transaction(function () use (
            $author, $branchId, $category, $amount, $date, $description, $source, $receiptPath
        ): Expense {
            $expense = Expense::create([
                'branch_id' => $branchId,
                'category_id' => $category->id,
                'amount' => $amount->toString(),
                'date' => ($date ?? CarbonImmutable::today())->toDateString(),
                'description' => $description,
                'source_type' => $source === null ? null : $source::class,
                'source_id' => $source?->getKey(),
                'receipt_path' => $receiptPath,
                'created_by' => $author->id,
            ]);

            $this->cash->record(
                $author,
                $branchId,
                CashCategory::Expense,
                $amount,
                source: $expense,
                description: $description,
            );

            return $expense;
        });
    }
}
