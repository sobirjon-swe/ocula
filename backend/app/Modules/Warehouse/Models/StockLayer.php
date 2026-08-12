<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Models;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Models\Location;
use App\Modules\Warehouse\Enums\LayerSource;
use App\Support\Concerns\BelongsToBranch;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FIFO qatlami — PROJECT.md 7.20.
 *
 * Har kirim alohida qatlam ochadi, chiqim eng eskisidan sarflaydi.
 * Qatlam `(variant_id, location_id)` kesimida.
 *
 * `Immutable` trait bu yerda **ataylab yo'q**: `quantity_remaining`
 * qatlam sarflanganda o'zgaradi (SCHEMA.md §3 dagi istisno). Sarflash
 * tarixi `stock_layer_consumptions` da to'liq saqlanadi, shuning uchun
 * audit izi yo'qolmaydi.
 *
 * @property int $id
 * @property int $branch_id
 * @property int $location_id
 * @property int $variant_id
 * @property LayerSource $source_type
 * @property int|null $source_id
 * @property int $movement_id
 * @property Money $unit_cost
 * @property int $quantity_in
 * @property int $quantity_remaining
 * @property CarbonImmutable|null $received_at
 */
class StockLayer extends Model
{
    use BelongsToBranch;

    public const UPDATED_AT = null;

    protected $fillable = [
        'branch_id', 'location_id', 'variant_id', 'source_type', 'source_id',
        'movement_id', 'unit_cost', 'quantity_in', 'quantity_remaining', 'received_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_type' => LayerSource::class,
            'unit_cost' => MoneyCast::class,
            'quantity_in' => 'integer',
            'quantity_remaining' => 'integer',
            'received_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<StockMovement, $this>
     */
    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'movement_id');
    }

    /**
     * @return HasMany<StockLayerConsumption, $this>
     */
    public function consumptions(): HasMany
    {
        return $this->hasMany(StockLayerConsumption::class, 'layer_id');
    }

    /**
     * FIFO tartibida sarflanmagan qatlamlar.
     *
     * Tartib `received_at, id` — bir soniyada kelgan ikki qatlam ham
     * barqaror (deterministik) tartibda sarflansin.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeAvailable(Builder $query, int $variantId, int $locationId): void
    {
        $query->where('variant_id', $variantId)
            ->where('location_id', $locationId)
            ->where('quantity_remaining', '>', 0)
            ->orderBy('received_at')
            ->orderBy('id');
    }
}
