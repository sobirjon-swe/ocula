<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Yetkazib beruvchi — SCHEMA.md §3, PROJECT.md 7.15.
 *
 * @property int $id
 * @property string $name
 * @property string|null $phone
 * @property int $payment_terms_days
 * @property bool $is_active
 */
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    protected $fillable = ['name', 'phone', 'payment_terms_days', 'notes', 'is_active'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'payment_terms_days' => 0,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_terms_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Purchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }
}
