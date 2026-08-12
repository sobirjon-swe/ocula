<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Core\Models\Branch;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Database\Factories\PriceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Narx — SCHEMA.md §2, ANALIZ 3.16.
 *
 * Filial narxi global narxdan ustun. Yangi narx qo'yilganda avvalgisining
 * `valid_to` yopiladi — shunda o'tgan oy hisoboti keyingi narx
 * o'zgarishidan buzilmaydi.
 *
 * @property int $id
 * @property int $variant_id
 * @property int|null $branch_id
 * @property Money $price
 */
class Price extends Model
{
    /** @use HasFactory<PriceFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'variant_id', 'branch_id', 'price', 'valid_from', 'valid_to', 'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => MoneyCast::class,
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
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
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Shu paytda amal qilayotgan narxlar.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $now = now();

        $query->where('valid_from', '<=', $now)
            ->where(function (Builder $inner) use ($now): void {
                $inner->whereNull('valid_to')->orWhere('valid_to', '>', $now);
            });
    }

    /**
     * Filial narxi bo'lsa u, bo'lmasa global narx (ANALIZ 3.16).
     */
    public static function resolveFor(int $variantId, ?int $branchId): ?self
    {
        $pick = static fn (?int $branch): ?self => static::query()
            ->where('variant_id', $variantId)
            ->when(
                $branch === null,
                static fn (Builder $q) => $q->whereNull('branch_id'),
                static fn (Builder $q) => $q->where('branch_id', $branch),
            )
            ->current()
            ->orderByDesc('valid_from')
            ->first();

        return ($branchId !== null ? $pick($branchId) : null) ?? $pick(null);
    }
}
