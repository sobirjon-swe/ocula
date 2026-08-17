<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Modules\Payroll\Enums\BonusBase;
use Carbon\CarbonImmutable;
use Database\Factories\BonusRuleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mukofot qoidasi — SCHEMA.md §9, PROJECT.md 7.19.
 *
 * `user_id` bo'lgan qoida rol qoidasidan **ustun** — `BonusRuleResolver`
 * shuni qidiradi. `valid_from`/`valid_to` — o'zgartirish tarixi
 * saqlanadi, o'tgan oy hisoboti keyingi o'zgarishdan buzilmasin (7.19).
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $role
 * @property int|null $branch_id
 * @property BonusBase $base
 * @property string $percent
 * @property CarbonImmutable $valid_from
 * @property CarbonImmutable|null $valid_to
 * @property int $created_by
 */
class BonusRule extends Model
{
    /** @use HasFactory<BonusRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'role', 'branch_id', 'base', 'percent',
        'valid_from', 'valid_to', 'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base' => BonusBase::class,
            'percent' => 'decimal:2',
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeActiveOn(Builder $query, CarbonImmutable $date): void
    {
        $query->where('valid_from', '<=', $date->toDateString())
            ->where(function (Builder $q) use ($date): void {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $date->toDateString());
            });
    }
}
