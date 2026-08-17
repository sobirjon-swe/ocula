<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\DebtStatus;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Order;
use App\Support\Concerns\BelongsToBranch;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Carbon\CarbonImmutable;
use Database\Factories\DebtFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Qarz registri — SCHEMA.md, PROJECT.md 7.6, BOSQICH-10.md §10a.
 *
 * **Qo'lda ochilmaydi.** `amount`/`paid` — `orders.total`/`orders.paid`
 * ning ko'zgusi, `DebtRegistry::syncForOrder()` tomonidan yoziladi
 * (buyurtma qanday `WorkOrder` tug'dirsa, Bosqich 6, shunday). Qoldiq
 * har doim `amount - paid`, bu `orders.debt` bilan mos keladi.
 *
 * @property int $id
 * @property int $customer_id
 * @property int|null $order_id
 * @property int $branch_id
 * @property Money $amount
 * @property Money $paid
 * @property CarbonImmutable $due_date
 * @property DebtStatus $status
 * @property string|null $write_off_reason
 * @property CarbonImmutable|null $closed_at
 * @property int|null $approved_by
 * @property int|null $created_by
 */
class Debt extends Model
{
    /** @use HasFactory<DebtFactory> */
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'customer_id', 'order_id', 'branch_id', 'amount', 'paid', 'due_date',
        'status', 'write_off_reason', 'closed_at', 'approved_by', 'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'paid' => MoneyCast::class,
            'due_date' => 'date',
            'status' => DebtStatus::class,
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<DebtReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(DebtReminder::class);
    }

    public function remaining(): Money
    {
        $remaining = $this->amount->minus($this->paid);

        return $remaining->isNegative() ? Money::zero() : $remaining;
    }
}
