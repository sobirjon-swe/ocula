<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Brend — SCHEMA.md §2.
 *
 * Brend nomi tarjima qilinmaydi (PROJECT.md §10) — brendlar lotin
 * yozuvida yagona nom bilan yuritiladi.
 *
 * @property int $id
 * @property string $name
 */
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory;

    protected $fillable = ['name', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
