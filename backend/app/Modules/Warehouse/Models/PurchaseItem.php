<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Models;

use App\Modules\Catalog\Models\ProductVariant;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kirim hujjati satri — SCHEMA.md §3.
 *
 * `cost_price` to'g'ridan-to'g'ri FIFO qatlamining `unit_cost` iga
 * aylanadi (7.20).
 *
 * @property int $id
 * @property int $purchase_id
 * @property int $variant_id
 * @property int $quantity
 * @property Money $cost_price
 * @property Money $total
 */
class PurchaseItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['purchase_id', 'variant_id', 'quantity', 'cost_price', 'total'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'cost_price' => MoneyCast::class,
            'total' => MoneyCast::class,
        ];
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
