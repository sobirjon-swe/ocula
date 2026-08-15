<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Shift;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Enums\PaymentMethod;
use App\Modules\Sales\Enums\PaymentTxStatus;
use App\Support\Concerns\BelongsToBranch;
use App\Support\Concerns\Immutable;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * To'lov — SCHEMA.md §4, PROJECT.md 7.21.
 *
 * **Insert-only**: `Immutable` trait `UPDATE` va `DELETE` ni to'sadi.
 * Xato bo'lsa storno — manfiy summali yangi yozuv `reverses_id` bilan.
 *
 * `amount` ishorali: qaytarish va storno manfiy. Shu sababli
 * buyurtmaning to'langan summasi shunchaki yig'indi bo'ladi.
 *
 * @property int $id
 * @property int|null $order_id
 * @property int|null $customer_id
 * @property int $branch_id
 * @property int|null $shift_id
 * @property Money $amount
 * @property PaymentMethod $method
 * @property PaymentTxStatus $status
 * @property int|null $reverses_id
 * @property string|null $reason
 * @property int $received_by
 * @property int|null $collected_by
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $created_at
 */
class Payment extends Model
{
    use BelongsToBranch, Immutable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'order_id', 'customer_id', 'branch_id', 'shift_id', 'amount',
        'method', 'status', 'reverses_id', 'reason', 'received_by',
        'collected_by', 'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'method' => PaymentMethod::class,
            'status' => PaymentTxStatus::class,
            'paid_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    /**
     * Allaqachon storno qilinganmi — ikki marta stornoning oldini oladi.
     */
    public function isReversed(): bool
    {
        return self::query()->withoutGlobalScopes()->where('reverses_id', $this->id)->exists();
    }

    /**
     * Naqd to'lovmi — faqat shunday to'lov kassaga tushadi (ENUMS.md §4).
     */
    public function entersCashRegister(): bool
    {
        return $this->method->entersCashRegister();
    }

    public function isRefund(): bool
    {
        return $this->amount->isNegative();
    }
}
