<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Models;

use App\Modules\Core\Models\User;
use App\Modules\Payroll\Enums\BonusStatus;
use App\Modules\Sales\Models\Order;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Carbon\CarbonImmutable;
use Database\Factories\BonusEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mukofot yozuvi — SCHEMA.md §9, PROJECT.md 7.12.
 *
 * Moliyaviy maydonlar (`amount`, `base_amount`, `user_id`, `order_id`)
 * **hech qachon o'zgartirilmaydi** — tuzatish faqat storno
 * (`reverses_id`) orqali (7.21), `BonusAccrual`/`BonusAccrual::reverseForOrder()`
 * yagona yozuvchi joy. `status` esa hayot davri bosqichini bildiradi
 * (`accrued → approved → paid`) va shu maydon uchun `update()` ataylab
 * ochiq qoldirilgan — bu daftar tuzatishi emas, holat o'tishi (`Immutable`
 * trait shu sabab qo'llanilmaydi, boshqa modullardagi kassa/qarz
 * daftaridan farqli).
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $order_id
 * @property int|null $rule_id
 * @property string $period
 * @property Money $base_amount
 * @property string $percent
 * @property Money $amount
 * @property BonusStatus $status
 * @property int|null $reverses_id
 * @property CarbonImmutable $calculated_at
 * @property CarbonImmutable $created_at
 */
class BonusEntry extends Model
{
    /** @use HasFactory<BonusEntryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'order_id', 'rule_id', 'period', 'base_amount',
        'percent', 'amount', 'status', 'reverses_id', 'calculated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_amount' => MoneyCast::class,
            'percent' => 'decimal:2',
            'amount' => MoneyCast::class,
            'status' => BonusStatus::class,
            'calculated_at' => 'datetime',
            'created_at' => 'datetime',
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
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<BonusRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(BonusRule::class, 'rule_id');
    }

    /**
     * @return BelongsTo<BonusEntry, $this>
     */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    public function isReversed(): bool
    {
        return self::query()->where('reverses_id', $this->id)->exists();
    }
}
