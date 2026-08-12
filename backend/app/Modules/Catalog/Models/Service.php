<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\ServiceType;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Xizmat — SCHEMA.md §2.
 *
 * Ombordan o'tmaydi, lekin chekka va buyurtmaga tushadi
 * (`order_items.itemable_type = Service`).
 *
 * @property int $id
 * @property string $name
 * @property Money $price
 * @property ServiceType $type
 */
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    protected $fillable = ['name', 'price', 'duration_min', 'type', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => MoneyCast::class,
            'type' => ServiceType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
