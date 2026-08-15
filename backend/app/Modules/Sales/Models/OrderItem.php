<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Modules\Sales\Enums\CostSource;
use App\Modules\Warehouse\Models\StockMovement;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Buyurtma satri — SCHEMA.md §4.
 *
 * Polimorf: `ProductVariant` (ombordan) yoki `Service` (ombordan
 * o'tmaydi). Individual linza ham xizmat satri bo'lib keladi, faqat
 * uning `custom_lens_params` i to'ldiriladi va tannarxi qo'lda
 * kiritiladi (`cost_source = manual`, ANALIZ 3.9).
 *
 * `cost_total` **topshirish paytida** yoziladi: shu paytda FIFO
 * qatlamlari sarflanadi va haqiqiy tannarx ma'lum bo'ladi (7.20).
 *
 * @property int $id
 * @property int $order_id
 * @property string $itemable_type
 * @property int $itemable_id
 * @property int $quantity
 * @property Money $price
 * @property Money $discount
 * @property Money $total
 * @property Money $cost_total
 * @property CostSource $cost_source
 * @property int|null $purchase_item_id
 * @property array<string, mixed>|null $custom_lens_params
 * @property int|null $movement_id
 * @property CarbonImmutable|null $created_at
 */
class OrderItem extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'order_id', 'itemable_type', 'itemable_id', 'quantity', 'price',
        'discount', 'total', 'cost_total', 'cost_source', 'purchase_item_id',
        'custom_lens_params', 'movement_id',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'discount' => '0.00',
        'cost_total' => '0.00',
        'cost_source' => 'fifo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price' => MoneyCast::class,
            'discount' => MoneyCast::class,
            'total' => MoneyCast::class,
            'cost_total' => MoneyCast::class,
            'cost_source' => CostSource::class,
            'custom_lens_params' => 'array',
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
     * Sotilgan narsa: `ProductVariant` yoki `Service`.
     *
     * @return MorphTo<Model, $this>
     */
    public function itemable(): MorphTo
    {
        return $this->morphTo('itemable');
    }

    /**
     * Shu satr tug'dirgan ombor chiqimi (bo'lsa).
     *
     * @return BelongsTo<StockMovement, $this>
     */
    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'movement_id');
    }

    /**
     * @return HasMany<OrderReturnItem, $this>
     */
    public function returnItems(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class);
    }

    /**
     * Chegirmadan keyingi birlik narxi — qaytarishda shu bo'yicha pul
     * hisoblanadi, aks holda mijozga chegirmasiz summa qaytarilardi.
     */
    public function unitNetPrice(): Money
    {
        return $this->total->dividedBy($this->quantity);
    }

    /**
     * Birlik tannarxi — qaytarilgan tovar omborga **sotilgandagi**
     * tannarx bilan qaytadi (SCHEMA.md §4).
     */
    public function unitCost(): Money
    {
        return $this->cost_total->dividedBy($this->quantity);
    }

    /**
     * Allaqachon qaytarilgan miqdor — bir tovarni ikki marta
     * qaytarishning oldini oladi.
     */
    public function returnedQuantity(): int
    {
        return (int) $this->returnItems()->sum('quantity');
    }
}
