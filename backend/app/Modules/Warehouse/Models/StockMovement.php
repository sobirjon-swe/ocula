<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Models;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Enums\MovementType;
use App\Support\Concerns\BelongsToBranch;
use App\Support\Concerns\Immutable;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Ombor harakati — PROJECT.md 7.1, 7.20, 7.21.
 *
 * **Qoldiq = shu yozuvlar yig'indisi.** `stock_balances` faqat kesh.
 *
 * Insert-only: `Immutable` trait `UPDATE` va `DELETE` ni model
 * darajasida to'sadi. Xato bo'lsa storno — `StockLedger::reverse()`.
 *
 * @property int $id
 * @property int $branch_id
 * @property int $location_id
 * @property int $variant_id
 * @property MovementType $type
 * @property int $quantity
 * @property Money $cost_total
 * @property bool $cost_incomplete
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int|null $reverses_id
 * @property string|null $reason
 * @property int $created_by
 * @property CarbonImmutable|null $created_at
 */
class StockMovement extends Model
{
    use BelongsToBranch, Immutable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'branch_id', 'location_id', 'variant_id', 'type', 'quantity',
        'cost_total', 'cost_incomplete', 'source_type', 'source_id',
        'reverses_id', 'reason', 'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'quantity' => 'integer',
            'cost_total' => MoneyCast::class,
            'cost_incomplete' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Qaysi hujjatdan kelib chiqqan — kirim, buyurtma, transfer…
     *
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo('source');
    }

    /**
     * Storno qilingan asl harakat (7.21).
     *
     * @return BelongsTo<StockMovement, $this>
     */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    /**
     * Shu harakat ochgan qatlam (kirim bo'lsa).
     *
     * @return HasMany<StockLayer, $this>
     */
    public function layers(): HasMany
    {
        return $this->hasMany(StockLayer::class, 'movement_id');
    }

    /**
     * Shu harakat sarflagan qatlamlar (chiqim bo'lsa).
     *
     * @return HasMany<StockLayerConsumption, $this>
     */
    public function consumptions(): HasMany
    {
        return $this->hasMany(StockLayerConsumption::class, 'movement_id');
    }

    public function isIncoming(): bool
    {
        return $this->quantity > 0;
    }

    /**
     * Allaqachon storno qilinganmi — ikki marta stornoning oldini oladi.
     */
    public function isReversed(): bool
    {
        return self::query()->where('reverses_id', $this->id)->exists();
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeForVariantAt(Builder $query, int $variantId, int $locationId): void
    {
        $query->where('variant_id', $variantId)->where('location_id', $locationId);
    }
}
