<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Models;

use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Delivery\Models\Trip;
use App\Modules\Warehouse\Enums\DeliveryMethod;
use App\Modules\Warehouse\Enums\TransferStatus;
use App\Modules\Warehouse\Scopes\TwoSidedBranchScope;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Carbon\CarbonImmutable;
use Database\Factories\TransferFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Filiallararo transfer — SCHEMA.md §3, PROJECT.md 7.4, 7.10.
 *
 * Ikki bosqichli: jo'natilganda tovar jo'natuvchidan chiqib transitda
 * turadi, qabul qilinganda yangi filialga kiradi. Oradagi vaqtda
 * qoldiq yo'qolmaydi — u `transit_location_id` da ko'rinadi.
 *
 * `BelongsToBranch` **ishlatilmaydi**: hujjatning ikkita tomoni bor,
 * ikkalasi ham uni ko'rishi kerak (`TwoSidedBranchScope`).
 *
 * @property int $id
 * @property string $number
 * @property int $from_location_id
 * @property int $to_location_id
 * @property int|null $transit_location_id
 * @property TransferStatus $status
 * @property DeliveryMethod $delivery_method
 * @property int|null $driver_id
 * @property int|null $carrier_user_id
 * @property Money|null $taxi_cost
 * @property string|null $taxi_receipt_path
 * @property int|null $trip_id
 * @property int|null $sent_by
 * @property CarbonImmutable|null $sent_at
 * @property int|null $received_by
 * @property CarbonImmutable|null $received_at
 * @property bool $has_discrepancy
 * @property string|null $note
 * @property int $created_by
 * @property CarbonImmutable|null $created_at
 */
class Transfer extends Model
{
    /** @use HasFactory<TransferFactory> */
    use HasFactory;

    protected $fillable = [
        'number', 'from_location_id', 'to_location_id', 'transit_location_id',
        'status', 'delivery_method', 'driver_id', 'carrier_user_id',
        'taxi_cost', 'taxi_receipt_path', 'trip_id', 'sent_by', 'sent_at',
        'received_by', 'received_at', 'has_discrepancy', 'note', 'created_by',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'has_discrepancy' => false,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(
            new TwoSidedBranchScope('from_location_id', 'to_location_id', throughLocations: true),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'delivery_method' => DeliveryMethod::class,
            'taxi_cost' => MoneyCast::class,
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
            'has_discrepancy' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function transitLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'transit_location_id');
    }

    /**
     * @return HasMany<TransferItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(TransferItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'carrier_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Yo'l varaqasi — Bosqich 8, BOSQICH-8.md §2. `own_driver` usulida
     * jo'natilgan transfer haydovchining reysiga bog'lanadi.
     *
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * Yetkazuvchi xodim — haydovchi yoki o'zi olib borgan xodim (7.10).
     */
    public function carrierId(): ?int
    {
        return $this->driver_id ?? $this->carrier_user_id;
    }

    /**
     * Yo'lda turgan transferlar — "hozir nima yo'lda" savoliga javob.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeOnTheRoad(Builder $query): void
    {
        $query->whereIn('status', [
            TransferStatus::Sent->value,
            TransferStatus::InTransit->value,
        ]);
    }
}
