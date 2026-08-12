<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\LensCoating;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tovar varianti — SCHEMA.md §2, PROJECT.md 7.2.
 *
 * Linza uchun: `products` = model ("Hoya 1.61 HMC"), `product_variants`
 * = konkret kombinatsiya (SPH −2.25, CYL −0.75). Variant faqat ombordan
 * haqiqatan o'tganda yaratiladi (lazy creation).
 *
 * Individual buyurtma linzalari bu jadvalga umuman tushmaydi (7.13) —
 * ular `order_items.custom_lens_params` da yashaydi.
 *
 * @property int $id
 * @property int $product_id
 * @property array<string, mixed> $attributes_json
 * @property LensCoating|null $coating
 */
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id', 'sku', 'barcode', 'attributes',
        'sph', 'cyl', 'axis', 'add', 'index', 'coating',
        'diameter', 'color', 'size', 'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'coating' => LensCoating::class,
            'axis' => 'integer',
            'diameter' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<Price, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(Price::class, 'variant_id');
    }

    /**
     * Retseptga mos yozuv: `SPH −2.25 · CYL −0.75 · AXIS 90`.
     */
    public function opticalLabel(): string
    {
        $parts = [];

        foreach (['sph' => 'SPH', 'cyl' => 'CYL'] as $column => $label) {
            $value = $this->getAttribute($column);

            if ($value !== null) {
                $parts[] = $label.' '.($value > 0 ? '+' : '').$value;
            }
        }

        if ($this->getAttribute('axis') !== null) {
            $parts[] = 'AXIS '.$this->getAttribute('axis');
        }

        return implode(' · ', $parts);
    }
}
