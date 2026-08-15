<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Shift;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Enums\OrderDeliveryType;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Enums\OrderType;
use App\Modules\Sales\Enums\PaymentStatus;
use App\Support\Concerns\BelongsToBranch;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Buyurtma yoki chek — SCHEMA.md §4, PROJECT.md 7.3, 7.8.
 *
 * Ikkita mustaqil o'q: `status` (bajarilish) va `payment_status`
 * (to'lov). `ready` + `partial` normal holat.
 *
 * `revenue_recognized_at` — daromad sanasi (7.8). Foyda hisoboti shu
 * ustun bo'yicha, `created_at` bo'yicha emas.
 *
 * @property int $id
 * @property string $number
 * @property int $branch_id
 * @property int|null $customer_id
 * @property int|null $shift_id
 * @property OrderType $type
 * @property OrderStatus $status
 * @property PaymentStatus $payment_status
 * @property int|null $prescription_id
 * @property Money $subtotal
 * @property Money $discount
 * @property Money $rounding
 * @property Money $total
 * @property Money $paid
 * @property Money $debt
 * @property Money $cost_total
 * @property CarbonImmutable|null $due_date
 * @property OrderDeliveryType $delivery_type
 * @property CarbonImmutable|null $status_changed_at
 * @property CarbonImmutable|null $revenue_recognized_at
 * @property CarbonImmutable|null $delivered_at
 * @property CarbonImmutable|null $abandoned_at
 * @property CarbonImmutable|null $cancelled_at
 * @property int|null $discount_approved_by
 * @property int $created_by
 * @property CarbonImmutable|null $created_at
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'number', 'branch_id', 'customer_id', 'shift_id', 'type', 'status',
        'payment_status', 'prescription_id', 'subtotal', 'discount', 'rounding',
        'total', 'paid', 'debt', 'cost_total', 'due_date', 'delivery_type',
        'status_changed_at', 'revenue_recognized_at', 'delivered_at',
        'abandoned_at', 'cancelled_at', 'discount_approved_by', 'created_by',
    ];

    /**
     * Migratsiyadagi `default` bazadan qayta o'qilgandagina qaytadi —
     * yangi model esa darhol javobga chiqadi.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'subtotal' => '0.00',
        'discount' => '0.00',
        'rounding' => '0.00',
        'total' => '0.00',
        'paid' => '0.00',
        'debt' => '0.00',
        'cost_total' => '0.00',
        'status' => 'new',
        'payment_status' => 'unpaid',
        'delivery_type' => 'pickup',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'delivery_type' => OrderDeliveryType::class,
            'subtotal' => MoneyCast::class,
            'discount' => MoneyCast::class,
            'rounding' => MoneyCast::class,
            'total' => MoneyCast::class,
            'paid' => MoneyCast::class,
            'debt' => MoneyCast::class,
            'cost_total' => MoneyCast::class,
            'due_date' => 'date',
            'status_changed_at' => 'datetime',
            'revenue_recognized_at' => 'datetime',
            'delivered_at' => 'datetime',
            'abandoned_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<OrderReturn, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    /**
     * Haqiqatan to'langan summa — `payments` yig'indisi.
     *
     * `paid` ustuni shu qiymatning keshi (`stock_balances` ombor uchun
     * qanday bo'lsa, shunday). Storno va qaytarish manfiy yozuv bo'lgani
     * uchun yig'indi o'z-o'zidan to'g'ri chiqadi.
     */
    public function paidAmount(): Money
    {
        return Money::of((string) $this->payments()->sum('amount'));
    }

    /**
     * Topshirilgan va daromad tan olingan buyurtmalar (7.8-B).
     *
     * @param  Builder<$this>  $query
     */
    public function scopeRevenueRecognized(Builder $query): void
    {
        $query->whereNotNull('revenue_recognized_at');
    }

    /**
     * "Bajarilmagan buyurtmalar majburiyati" (7.8) — pul kassada,
     * tovar hali mijozda emas.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeOutstanding(Builder $query): void
    {
        $query->whereNotIn('status', [
            OrderStatus::Delivered->value,
            OrderStatus::Closed->value,
            OrderStatus::Cancelled->value,
        ]);
    }
}
