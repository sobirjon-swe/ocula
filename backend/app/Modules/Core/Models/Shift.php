<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use App\Modules\Core\Enums\ShiftStatus;
use App\Support\Concerns\BelongsToBranch;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Carbon\CarbonImmutable;
use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Smena — PROJECT.md §6.1, 5.2.
 *
 * Kunlik hisobot **shu jadval bo'yicha**, kalendar sanasi bo'yicha emas
 * (§15 #24): 22:00 dan keyingi yozuv ertangi kunga tushib qolmasligi kerak.
 *
 * Yopilishda sotuvchi seyfdagi haqiqiy naqdni kiritadi, tizim farqni
 * (kamomad/ortiqcha) hisoblab qo'yadi.
 *
 * @property int $id
 * @property int $branch_id
 * @property ShiftStatus $status
 * @property CarbonImmutable|null $opened_at
 * @property CarbonImmutable|null $closed_at
 * @property Money $opening_cash
 * @property Money|null $expected_cash
 * @property Money|null $actual_cash
 * @property Money|null $difference
 * @property string|null $note
 * @property-read Branch|null $branch
 * @property-read User|null $openedBy
 * @property-read User|null $closedBy
 */
class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id', 'opened_by', 'closed_by', 'opened_at', 'closed_at',
        'opening_cash', 'expected_cash', 'actual_cash', 'difference',
        'status', 'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShiftStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash' => MoneyCast::class,
            'expected_cash' => MoneyCast::class,
            'actual_cash' => MoneyCast::class,
            'difference' => MoneyCast::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Kamomad bormi (haqiqiy naqd hisoblangandan kam).
     */
    public function hasShortage(): bool
    {
        return $this->difference?->isNegative() ?? false;
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', [ShiftStatus::Open, ShiftStatus::Closing]);
    }
}
