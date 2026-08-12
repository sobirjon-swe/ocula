<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use App\Modules\Core\Enums\BranchType;
use App\Modules\Core\Enums\LocationType;
use Carbon\CarbonImmutable;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Filial — PROJECT.md §6.1, SCHEMA.md §1.
 *
 * `code` hujjat raqamida ishlatiladi: `A-2608-00147` (ANALIZ 3.12).
 * Shuning uchun u qisqa, noyob va o'zgarmas bo'lishi kerak.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property BranchType $type
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $open_time
 * @property string|null $close_time
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 */
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'type', 'address', 'lat', 'lng',
        'open_time', 'close_time', 'phone', 'is_active',
    ];

    /**
     * Migratsiyadagi `default` — yangi model bazadan o'qilmasdan turib
     * javobga chiqqanda ham to'g'ri qiymat bo'lsin.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BranchType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Location, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /**
     * @return HasMany<Shift, $this>
     */
    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    /**
     * @return HasMany<Device, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * Asosiy filiali shu bo'lgan xodimlar (`users.branch_id`).
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Qo'shimcha kirish huquqi berilgan xodimlar (`branch_user` — 3.11).
     *
     * @return BelongsToMany<User, $this>
     */
    public function additionalUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'branch_user');
    }

    /**
     * 1-versiyada har filialda bitta ombor bo'ladi va sotuv shundan
     * chiqadi (§15 #22).
     */
    public function warehouse(): ?Location
    {
        return $this->locations()
            ->where('type', LocationType::Warehouse)
            ->first();
    }

    public function isMain(): bool
    {
        return $this->type === BranchType::Main;
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
