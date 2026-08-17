<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Models;

use App\Modules\Core\Models\Branch;
use App\Modules\Delivery\Enums\ConfirmationStatus;
use App\Modules\Delivery\Enums\TripStopStatus;
use App\Modules\Delivery\Enums\TripStopType;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Order;
use App\Modules\Warehouse\Models\Transfer;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Carbon\CarbonImmutable;
use Database\Factories\TripStopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Yo'l varaqasi to'xtashi — SCHEMA.md, PROJECT.md 7.4, BOSQICH-8.md §4.
 *
 * Ikkita mustaqil tasdiq o'qi bor: `status` (haydovchi — "yetkazdim")
 * va `confirmation_status` (mijoz — "qabul qildim"). Ular bir-biriga
 * bog'liq emas: haydovchi yetkazgan bo'lsa ham, mijoz "yo'q" deb javob
 * berishi mumkin.
 *
 * @property int $id
 * @property int $trip_id
 * @property int $sequence
 * @property TripStopType $type
 * @property int|null $branch_id
 * @property int|null $customer_id
 * @property int|null $order_id
 * @property int|null $transfer_id
 * @property string|null $address
 * @property string|null $lat
 * @property string|null $lng
 * @property Money $cash_to_collect
 * @property TripStopStatus $status
 * @property CarbonImmutable|null $delivered_at
 * @property string|null $delivered_lat
 * @property string|null $delivered_lng
 * @property int|null $distance_m
 * @property string|null $photo_path
 * @property CarbonImmutable|null $customer_confirmed_at
 * @property ConfirmationStatus|null $confirmation_status
 * @property string|null $fail_reason
 */
class TripStop extends Model
{
    /** @use HasFactory<TripStopFactory> */
    use HasFactory;

    protected $fillable = [
        'trip_id', 'sequence', 'type', 'branch_id', 'customer_id', 'order_id',
        'transfer_id', 'address', 'lat', 'lng', 'cash_to_collect', 'status',
        'delivered_at', 'delivered_lat', 'delivered_lng', 'distance_m',
        'photo_path', 'customer_confirmed_at', 'confirmation_status', 'fail_reason',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'cash_to_collect' => '0.00',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TripStopType::class,
            'cash_to_collect' => MoneyCast::class,
            'status' => TripStopStatus::class,
            'delivered_at' => 'datetime',
            'distance_m' => 'integer',
            'customer_confirmed_at' => 'datetime',
            'confirmation_status' => ConfirmationStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Transfer, $this>
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }
}
