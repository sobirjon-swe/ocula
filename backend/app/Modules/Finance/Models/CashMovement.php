<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Shift;
use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\CashCategory;
use App\Modules\Finance\Enums\CashDirection;
use App\Support\Concerns\BelongsToBranch;
use App\Support\Concerns\Immutable;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Kassa harakati — SCHEMA.md §8, PROJECT.md 7.21.
 *
 * **Seyfdagi pul = shu yozuvlar yig'indisi** — `stock_movements` ning
 * ko'zgusi. Insert-only: `Immutable` trait `UPDATE` va `DELETE` ni
 * to'sadi, tuzatish faqat storno orqali.
 *
 * `amount` har doim musbat, ishorani `type` beradi — shuning uchun
 * yig'indi `signedAmount()` bo'yicha olinadi.
 *
 * @property int $id
 * @property int $branch_id
 * @property int|null $shift_id
 * @property CashDirection $type
 * @property CashCategory $category
 * @property Money $amount
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int|null $reverses_id
 * @property string|null $reason
 * @property string|null $description
 * @property int $created_by
 * @property CarbonImmutable|null $created_at
 */
class CashMovement extends Model
{
    use BelongsToBranch, Immutable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'branch_id', 'shift_id', 'type', 'category', 'amount',
        'source_type', 'source_id', 'reverses_id', 'reason',
        'description', 'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CashDirection::class,
            'category' => CashCategory::class,
            'amount' => MoneyCast::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Qaysi hujjatdan kelib chiqqan — to'lov, qaytarish, smena…
     *
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo('source');
    }

    /**
     * @return BelongsTo<CashMovement, $this>
     */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    /**
     * Ishorali summa: kirim `+`, chiqim `−`.
     */
    public function signedAmount(): Money
    {
        return $this->type === CashDirection::In ? $this->amount : $this->amount->negated();
    }

    public function isReversed(): bool
    {
        return self::query()->withoutGlobalScopes()->where('reverses_id', $this->id)->exists();
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeForShift(Builder $query, int $shiftId): void
    {
        $query->where('shift_id', $shiftId);
    }
}
