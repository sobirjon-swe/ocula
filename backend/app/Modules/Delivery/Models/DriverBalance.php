<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Models;

use App\Modules\Core\Models\User;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Haydovchi qo'lidagi pul keshi — SCHEMA.md, PROJECT.md §5.2, BOSQICH-8.md §3.
 *
 * **Faqat hosila**: `SUM(pending to'lovlar) − SUM(inkassatsiyalar)`.
 * Birlamchi kalit `driver_id` ning o'zi — bir haydovchida bitta qator.
 *
 * @property int $driver_id
 * @property Money $cash_amount
 */
class DriverBalance extends Model
{
    protected $primaryKey = 'driver_id';

    public $incrementing = false;

    public const CREATED_AT = null;

    protected $fillable = ['driver_id', 'cash_amount'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'cash_amount' => '0.00',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'driver_id' => 'integer',
            'cash_amount' => MoneyCast::class,
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
