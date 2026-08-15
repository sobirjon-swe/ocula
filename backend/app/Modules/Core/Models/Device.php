<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use App\Modules\Core\Enums\DeviceType;
use App\Support\Concerns\BelongsToBranch;
use Carbon\CarbonImmutable;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ro'yxatdan o'tgan qurilma — PROJECT.md 7.14.
 *
 * Umumiy planshetda hamma bitta akkauntdan ishlasa, ustaning brak
 * statistikasi va shifokor ko'rsatkichlari yolg'on bo'ladi. Yechim:
 * qurilma bir marta token bilan ro'yxatdan o'tadi, xodim esa 4 xonali
 * PIN bilan 2 soniyada almashadi — har amal aniq `user_id` ga yoziladi.
 *
 * @property int $id
 * @property int $branch_id
 * @property string $name
 * @property DeviceType $type
 * @property string $token
 * @property array<int, string> $allowed_roles
 * @property CarbonImmutable|null $last_seen_at
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 */
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id', 'name', 'type', 'token', 'allowed_roles', 'is_active',
    ];

    protected $hidden = ['token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DeviceType::class,
            'token' => 'hashed',
            'allowed_roles' => 'array',
            'last_seen_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
