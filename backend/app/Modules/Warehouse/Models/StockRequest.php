<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Models;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Models\Order;
use App\Modules\Warehouse\Enums\StockRequestStatus;
use App\Modules\Warehouse\Scopes\TwoSidedBranchScope;
use Carbon\CarbonImmutable;
use Database\Factories\StockRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ichki so'rov — SCHEMA.md §3.
 *
 * "Bu linza bizda yo'q, B filialda bor" degan holatni hujjatga
 * aylantiradi: so'rov tasdiqlanadi va undan transfer tug'iladi. Shunda
 * "nima uchun transfer qilindi" degan savolga javob qoladi.
 *
 * Transferdagi kabi ikkita tomoni bor, shuning uchun ko'rinish cheklovi
 * ikkala filial bo'yicha (`TwoSidedBranchScope`).
 *
 * @property int $id
 * @property int $from_branch_id
 * @property int $to_branch_id
 * @property int $variant_id
 * @property int $quantity
 * @property int|null $order_id
 * @property StockRequestStatus $status
 * @property int|null $transfer_id
 * @property int $requested_by
 * @property int|null $approved_by
 * @property CarbonImmutable|null $created_at
 */
class StockRequest extends Model
{
    /** @use HasFactory<StockRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'from_branch_id', 'to_branch_id', 'variant_id', 'quantity',
        'order_id', 'status', 'transfer_id', 'requested_by', 'approved_by',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TwoSidedBranchScope('from_branch_id', 'to_branch_id'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StockRequestStatus::class,
            'quantity' => 'integer',
        ];
    }

    /**
     * So'ragan filial.
     *
     * @return BelongsTo<Branch, $this>
     */
    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    /**
     * So'ralayotgan filial — tovar shu yerdan chiqadi.
     *
     * @return BelongsTo<Branch, $this>
     */
    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Transfer, $this>
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
