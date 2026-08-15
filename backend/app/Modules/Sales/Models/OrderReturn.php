<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Shift;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Enums\ReturnReason;
use App\Support\Concerns\BelongsToBranch;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Qaytarish hujjati — SCHEMA.md §4 (3.6).
 *
 * Klass nomi `Return` emas: `return` — PHP kalit so'zi. Jadval esa
 * sxemadagi holicha qoladi (`protected $table`).
 *
 * @property int $id
 * @property string $number
 * @property int $order_id
 * @property int $branch_id
 * @property int|null $shift_id
 * @property ReturnReason $reason
 * @property Money $amount
 * @property Money $cost_total
 * @property int|null $approved_by
 * @property int $created_by
 * @property CarbonImmutable|null $created_at
 */
class OrderReturn extends Model
{
    use BelongsToBranch;

    protected $table = 'returns';

    protected $fillable = [
        'number', 'order_id', 'branch_id', 'shift_id', 'reason',
        'amount', 'cost_total', 'approved_by', 'created_by',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'amount' => '0.00',
        'cost_total' => '0.00',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => ReturnReason::class,
            'amount' => MoneyCast::class,
            'cost_total' => MoneyCast::class,
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
     * @return HasMany<OrderReturnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class, 'return_id');
    }
}
