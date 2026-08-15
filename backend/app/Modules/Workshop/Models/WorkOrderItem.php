<?php

declare(strict_types=1);

namespace App\Modules\Workshop\Models;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Warehouse\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ish buyrug'ining materiali — SCHEMA.md §6.
 *
 * `consumed_movement_id` usta materialni sarflaganda yoziladi
 * (ANALIZ 3.4: `consume` — sotuv emas, lekin COGS ga tushadi). Shu
 * ustun bo'sh bo'lsa, material hali ombordan chiqmagan.
 *
 * @property int $id
 * @property int $work_order_id
 * @property int $variant_id
 * @property int $quantity
 * @property int|null $consumed_movement_id
 */
class WorkOrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'work_order_id', 'variant_id', 'quantity', 'consumed_movement_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * @return BelongsTo<StockMovement, $this>
     */
    public function consumedMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'consumed_movement_id');
    }

    public function isConsumed(): bool
    {
        return $this->consumed_movement_id !== null;
    }
}
