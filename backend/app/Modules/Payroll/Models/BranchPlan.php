<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use App\Modules\Core\Models\User;
use App\Modules\Payroll\Enums\PlanType;
use App\Support\Concerns\BelongsToBranch;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Database\Factories\BranchPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Filial oylik rejasi — SCHEMA.md §9, PROJECT.md 7.18.
 *
 * Reyting/progress hisobi Bosqich 10c (Analitika) ishi — bu yerda
 * faqat rejaning o'zi saqlanadi.
 *
 * @property int $id
 * @property int $branch_id
 * @property string $period
 * @property PlanType $type
 * @property Money $target_amount
 * @property int $created_by
 */
class BranchPlan extends Model
{
    /** @use HasFactory<BranchPlanFactory> */
    use BelongsToBranch, HasFactory;

    protected $fillable = ['branch_id', 'period', 'type', 'target_amount', 'created_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PlanType::class,
            'target_amount' => MoneyCast::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
