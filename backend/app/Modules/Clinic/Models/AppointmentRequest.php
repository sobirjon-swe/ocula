<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Models;

use App\Modules\Clinic\Enums\AppointmentRequestStatus;
use App\Modules\Core\Models\User;
use App\Support\Concerns\BelongsToBranch;
use Carbon\CarbonImmutable;
use Database\Factories\AppointmentRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Onlayn navbat so'rovi — BOSQICH-11.md.
 *
 * Landing saytdan kelgan "menga qo'ng'iroq qiling" so'rovi — mehmon
 * hisobsiz yozadi. Real `visits` navbatiga xodim tasdiqlagach qo'lda
 * ochiladi (Model docblock'idagi izoh, migratsiya).
 *
 * @property int $id
 * @property int $branch_id
 * @property string $name
 * @property string $phone
 * @property CarbonImmutable|null $preferred_date
 * @property string|null $note
 * @property AppointmentRequestStatus $status
 * @property int|null $handled_by
 * @property CarbonImmutable|null $handled_at
 * @property CarbonImmutable|null $created_at
 */
class AppointmentRequest extends Model
{
    /** @use HasFactory<AppointmentRequestFactory> */
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id', 'name', 'phone', 'preferred_date', 'note',
        'status', 'handled_by', 'handled_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'new',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'status' => AppointmentRequestStatus::class,
            'handled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
