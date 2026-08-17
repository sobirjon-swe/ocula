<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Modules\Core\Models\User;
use App\Support\Concerns\BelongsToBranch;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Carbon\CarbonImmutable;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Xarajat — SCHEMA.md, PROJECT.md §6.8, BOSQICH-10.md §10a.
 *
 * Yaratilganda `CreateExpense` kassadan chiqim ham yozadi
 * (`CashCategory::Expense`) — ikkalasi bir tranzaksiyada, aks holda
 * xarajat kassa daftaridan ajralib qolardi.
 *
 * `source_type`/`source_id` — avtomatik yaratilgan xarajatlar (masalan
 * taksi) qaysi hujjatdan kelib chiqqanini ko'rsatadi.
 *
 * @property int $id
 * @property int $branch_id
 * @property int $category_id
 * @property Money $amount
 * @property CarbonImmutable $date
 * @property string|null $description
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string|null $receipt_path
 * @property int|null $approved_by
 * @property int $created_by
 */
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id', 'category_id', 'amount', 'date', 'description',
        'source_type', 'source_id', 'receipt_path', 'approved_by', 'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo('source');
    }
}
