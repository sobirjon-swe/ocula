<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Models;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Models\Location;
use App\Support\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Qoldiq keshi — PROJECT.md 7.1.
 *
 * **Faqat hosila.** Haqiqat manbai — `stock_movements`. Bu jadval
 * `stock:rebuild-balances` bilan qayta hisoblanadi.
 *
 * Kompozit birlamchi kalit (`location_id`, `variant_id`) — Eloquent
 * bunday kalitni qo'llamaydi, shuning uchun avtoinkrement o'chirilgan
 * va yozuvlar `upsert` orqali yangilanadi.
 *
 * @property int $location_id
 * @property int $variant_id
 * @property int $branch_id
 * @property int $quantity
 */
class StockBalance extends Model
{
    use BelongsToBranch;

    public $incrementing = false;

    public const CREATED_AT = null;

    protected $primaryKey = null;

    protected $keyType = 'string';

    protected $fillable = ['location_id', 'variant_id', 'branch_id', 'quantity'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'updated_at' => 'datetime',
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
     * Qoldig'i bor yozuvlar — "qayerda bor" savoliga javob.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->where('quantity', '>', 0);
    }
}
