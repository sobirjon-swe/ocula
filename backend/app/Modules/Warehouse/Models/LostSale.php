<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Models;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Models\Customer;
use App\Modules\Warehouse\Enums\LostSaleReason;
use App\Support\Concerns\BelongsToBranch;
use App\Support\Concerns\Immutable;
use Carbon\CarbonImmutable;
use Database\Factories\LostSaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Yo'qotilgan savdo — SCHEMA.md, PROJECT.md 7.9, ANALIZ.md 3.8.
 *
 * "Har safar sotuvchi tovar qidirib topolmaganda yoki qoldiq 0
 * bo'lganda" yoziladi — oy oxirida qaysi tovarlar doim yetishmasligini
 * ko'rsatadi.
 *
 * @property int $id
 * @property int $branch_id
 * @property int|null $variant_id
 * @property string|null $search_term
 * @property int|null $customer_id
 * @property LostSaleReason $reason
 * @property int $created_by
 * @property CarbonImmutable $created_at
 */
class LostSale extends Model
{
    /** @use HasFactory<LostSaleFactory> */
    use BelongsToBranch, HasFactory, Immutable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'branch_id', 'variant_id', 'search_term', 'customer_id', 'reason', 'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => LostSaleReason::class,
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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
