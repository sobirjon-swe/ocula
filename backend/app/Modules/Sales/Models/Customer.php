<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Support\Enums\Locale;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Carbon\CarbonImmutable;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Mijoz — SCHEMA.md §4.
 *
 * `BelongsToBranch` ataylab **ishlatilmaydi**: mijoz butun tarmoqniki.
 * `branch_id` faqat "birinchi kelgan filial" ma'nosida yoziladi —
 * mijoz boshqa filialga kelganda ham o'sha kartochka topilishi kerak,
 * aks holda retsept tarixi va qarzi ko'rinmay qolardi.
 *
 * @property int $id
 * @property string $name
 * @property string $phone
 * @property CarbonImmutable|null $birth_date
 * @property int|null $telegram_id
 * @property Locale $locale
 * @property string|null $notes
 * @property Money $debt_balance
 * @property CarbonImmutable|null $first_visit_at
 * @property int $abandoned_orders_count
 * @property int|null $branch_id
 * @property int|null $created_by
 */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'phone', 'birth_date', 'telegram_id', 'locale',
        'notes', 'first_visit_at', 'branch_id', 'created_by',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'debt_balance' => '0.00',
        'locale' => 'uz-latn',
        'abandoned_orders_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'telegram_id' => 'integer',
            'locale' => Locale::class,
            'debt_balance' => MoneyCast::class,
            'first_visit_at' => 'datetime',
            'abandoned_orders_count' => 'integer',
        ];
    }

    /**
     * Birinchi kelgan filial — statistika uchun, cheklov uchun emas.
     *
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
