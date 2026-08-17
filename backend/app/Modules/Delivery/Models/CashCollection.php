<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Models;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Shift;
use App\Modules\Core\Models\User;
use App\Support\Concerns\Immutable;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inkassatsiya — haydovchidan kassaga pul topshirish hujjati.
 * SCHEMA.md (`collections`), PROJECT.md §5.2, 7.21, BOSQICH-8.md §3.
 *
 * Model nomi jadval nomidan ataylab farq qiladi (`collections` →
 * `CashCollection`): `Illuminate\Support\Collection` bilan
 * chalkashmasin.
 *
 * **Insert-only** — `Immutable` trait. Xato bo'lsa storno emas: bu
 * yerda tuzatish yo'q, chunki yozuv **haqiqiy pul harakatini** aks
 * ettiradi — kassaga tushgan pul orqaga qaytmaydi, faqat `cash_movements`
 * tomonidan alohida yozuv bilan tuzatiladi.
 *
 * @property int $id
 * @property int $driver_id
 * @property int $branch_id
 * @property int|null $shift_id
 * @property Money $amount
 * @property int $received_by
 * @property string|null $note
 * @property CarbonImmutable|null $created_at
 */
class CashCollection extends Model
{
    use Immutable;

    protected $table = 'collections';

    public const UPDATED_AT = null;

    protected $fillable = ['driver_id', 'branch_id', 'shift_id', 'amount', 'received_by', 'note'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
