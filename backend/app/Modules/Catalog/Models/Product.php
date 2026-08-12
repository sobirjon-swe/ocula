<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Enums\ProductType;
use App\Modules\Core\Models\User;
use App\Support\Text\Transliterator;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tovar — SCHEMA.md §2, PROJECT.md 7.13, 7.17.
 *
 * Katalog bo'sh boshlanadi va sotuv paytida "yurib" to'ldiriladi.
 * Erkin matn ishlatilmaydi: aks holda ombor qoldig'i, transfer,
 * inventarizatsiya va butun analitika ishlamay qoladi.
 *
 * `status = pending` tovar ham **sotiladi** va qoldiqda **bor** —
 * tasdiqlash savdoni to'smaydi (7.17).
 *
 * @property int $id
 * @property ProductType $type
 * @property string $name
 * @property string $search_key
 * @property ProductStatus $status
 * @property bool $quick_created
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type', 'name', 'brand_id', 'category_id', 'unit',
        'status', 'quick_created', 'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'quick_created' => 'boolean',
            'is_active' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // `search_key` — hosila ustun (ANALIZ 3.14). DB trigger emas,
        // chunki transliteratsiya qoidasi frontend bilan bitta manbadan
        // (App\Support\Text\Transliterator) bo'lishi shart.
        static::saving(function (self $product): void {
            $product->search_key = Transliterator::searchKey($product->name);
        });
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Dublikat birlashtirilganda asosiy tovar (7.17).
     *
     * @return BelongsTo<Product, $this>
     */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Sotish mumkin bo'lgan tovarlar — `rejected` dan boshqasi (7.17).
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSellable(Builder $query): void
    {
        $query->where('is_active', true)
            ->where('status', '!=', ProductStatus::Rejected);
    }

    /**
     * Direktor tasdig'ini kutayotganlar (7.17 — "Tasdiq kutilmoqda: 7 ta").
     *
     * @param  Builder<$this>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', ProductStatus::Pending);
    }

    /**
     * Fuzzy qidiruv — 7.13, ANALIZ 3.14.
     *
     * Qidiruv matni ham `search_key` ga aylantiriladi, shuning uchun
     * "Рэй бан" ham, "ray-ban" ham bir xil natija beradi. Trigram
     * o'xshashligi bo'yicha tartiblanadi (GIN indeks ishlaydi).
     *
     * @param  Builder<$this>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $key = Transliterator::searchKey($term);

        if ($key === '') {
            return;
        }

        $query->whereRaw('search_key % ?', [$key])
            ->orderByRaw('similarity(search_key, ?) DESC', [$key]);
    }
}
