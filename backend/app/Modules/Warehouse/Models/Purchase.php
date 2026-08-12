<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Models;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Enums\PurchaseStatus;
use App\Support\Concerns\BelongsToBranch;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Carbon\CarbonImmutable;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kirim hujjati — SCHEMA.md §3, PROJECT.md 7.20.
 *
 * `draft` holatida ombor **tegmaydi**; `received` bo'lganda har bir
 * satr uchun FIFO qatlami ochiladi. `received` dan orqaga qaytish yo'q
 * — xato bo'lsa storno (7.21).
 *
 * @property int $id
 * @property int $supplier_id
 * @property int $branch_id
 * @property int $location_id
 * @property string $number
 * @property CarbonImmutable|null $date
 * @property PurchaseStatus $status
 * @property Money $total
 * @property CarbonImmutable|null $received_at
 * @property int|null $received_by
 * @property string|null $note
 * @property int $created_by
 */
class Purchase extends Model
{
    /** @use HasFactory<PurchaseFactory> */
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'supplier_id', 'branch_id', 'location_id', 'number', 'date',
        'total', 'status', 'received_at', 'received_by', 'note', 'created_by',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'total' => '0.00',
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseStatus::class,
            'date' => 'date',
            'total' => MoneyCast::class,
            'received_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return HasMany<PurchaseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
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
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Satrlar yig'indisidan hujjat summasini qayta hisoblaydi.
     */
    public function recalculateTotal(): void
    {
        $total = Money::zero();

        foreach ($this->items()->get() as $item) {
            $total = $total->plus($item->total);
        }

        $this->update(['total' => $total->toString()]);
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeReceived(Builder $query): void
    {
        $query->where('status', PurchaseStatus::Received);
    }
}
