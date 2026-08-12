<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use App\Modules\Core\Enums\LocationType;
use App\Support\Concerns\BelongsToBranch;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Qoldiq "joyi" — PROJECT.md 7.1, SCHEMA.md §1.
 *
 * Ombor qoldig'i filialga emas, **location'ga** bog'lanadi: tovar yo'lda
 * (`transit`) yoki usta zaxirasida (`master`) ham turishi mumkin.
 *
 * `owner` polimorf (ANALIZ 3.2): `own_driver`/`by_hand` da `User`,
 * `taxi` da `Transfer` — chunki taksida javobgar odam yo'q (7.10).
 *
 * @property int $id
 * @property int $branch_id
 * @property LocationType $type
 * @property string $name
 */
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id', 'type', 'name', 'owner_type', 'owner_id', 'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LocationType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
