<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use App\Modules\Core\Enums\Role;
use App\Support\Contracts\HasBranchAccess;
use App\Support\Enums\Locale;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Xodim — PROJECT.md §4, SCHEMA.md §1.
 *
 * Login **telefon bo'yicha** (`phone` unique) — sotuvchi va haydovchida
 * email bo'lmasligi mumkin. Planshetda qo'shimcha `pin_hash` (7.14).
 *
 * Mijoz bu jadvalda **emas** — u alohida guard (`customers`).
 *
 * @property int $id
 * @property string $name
 * @property string $phone
 * @property string|null $email
 * @property string $password
 * @property int|null $branch_id
 * @property Money $debt_limit
 * @property Locale $locale
 * @property string|null $pin_hash
 * @property CarbonImmutable|null $pin_set_at
 * @property bool $is_active
 * @property CarbonImmutable|null $last_login_at
 * @property CarbonImmutable|null $created_at
 * @property-read Branch|null $branch
 */
class User extends Authenticatable implements HasBranchAccess
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * `BranchScope` har so'rovda chaqiriladi — pivotni bir marta o'qiymiz.
     *
     * @var array<int, int>|null
     */
    private ?array $cachedBranchIds = null;

    protected $fillable = [
        'name', 'phone', 'email', 'password', 'branch_id',
        'debt_limit', 'locale', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token', 'pin_hash'];

    /**
     * Migratsiyadagi `default` qiymatlar bazadan faqat qayta o'qilganda
     * qaytadi — yangi yaratilgan model esa darhol javobga chiqadi.
     * Shuning uchun ular shu yerda ham takrorlanadi.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'debt_limit' => '0.00',
        'locale' => 'uz-latn',
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'pin_hash' => 'hashed',
            'debt_limit' => MoneyCast::class,
            'locale' => Locale::class,
            'is_active' => 'boolean',
            'pin_set_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Asosiy filial (§4, ANALIZ 3.11).
     *
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Qo'shimcha kirish huquqi berilgan filiallar (`branch_user`).
     *
     * Haydovchi, omborchi va filiallar orasida ko'chib yuruvchi sotuvchi
     * uchun. Bosqich 1 da bu pivot bo'sh bo'lishi mumkin.
     *
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_user');
    }

    /**
     * `director` va `accountant` barcha filiallarni ko'radi (§4).
     */
    public function canAccessAllBranches(): bool
    {
        $roles = array_map(
            static fn (Role $role): string => $role->value,
            Role::seeingAllBranches(),
        );

        return $this->hasAnyRole($roles);
    }

    /**
     * @return array<int, int>
     */
    public function accessibleBranchIds(): array
    {
        if ($this->cachedBranchIds !== null) {
            return $this->cachedBranchIds;
        }

        $ids = [];

        if ($this->branch_id !== null) {
            $ids[] = (int) $this->branch_id;
        }

        foreach ($this->branches()->pluck('branches.id') as $id) {
            $ids[] = (int) $id;
        }

        return $this->cachedBranchIds = array_values(array_unique($ids));
    }

    /**
     * Planshetda PIN o'rnatilganmi (7.14).
     */
    public function hasPin(): bool
    {
        return $this->getAttribute('pin_hash') !== null;
    }
}
