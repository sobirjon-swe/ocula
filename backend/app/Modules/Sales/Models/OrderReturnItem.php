<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Warehouse\Models\StockMovement;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Qaytarish satri — SCHEMA.md §4 (3.6).
 *
 * `restock` har bir satr uchun alohida hal qilinadi: bitta ko'zoynak
 * sotuvga yaroqli bo'lib qaytishi, ikkinchisi sinib kelishi mumkin.
 *
 * @property int $id
 * @property int $return_id
 * @property int $order_item_id
 * @property int|null $variant_id
 * @property int $quantity
 * @property Money $amount
 * @property Money $cost_total
 * @property bool $restock
 * @property int|null $movement_id
 */
class OrderReturnItem extends Model
{
    /**
     * Klass `OrderReturn` dan hosil bo'lgani uchun Laravel jadval nomini
     * `order_return_items` deb topardi — sxemada esa u `return_items`.
     */
    protected $table = 'return_items';

    public $timestamps = false;

    protected $fillable = [
        'return_id', 'order_item_id', 'variant_id', 'quantity',
        'amount', 'cost_total', 'restock', 'movement_id',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'cost_total' => '0.00',
        'restock' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'amount' => MoneyCast::class,
            'cost_total' => MoneyCast::class,
            'restock' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<OrderReturn, $this>
     */
    public function return(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class, 'return_id');
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * Omborga qaytish harakati (`restock = true` bo'lganda).
     *
     * @return BelongsTo<StockMovement, $this>
     */
    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'movement_id');
    }
}
