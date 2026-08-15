<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Models;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tiketning boshqa filialga o'tkazilishi — SCHEMA.md §5, PROJECT.md 7.11.
 *
 * 7.11 ning butun mohiyati tiketni **bitta filialda ushlab turish**.
 * Uni ko'chirish qoidadan chekinish, shuning uchun har ko'chirish
 * sababi bilan yozib qo'yiladi: keyin "nega B filialda A ning retsepti
 * bo'yicha ko'zoynak yasaldi" degan savolga javob bo'lsin.
 *
 * Yozuv insert-only bo'lib qoladi — `updated_at` yo'q.
 *
 * @property int $id
 * @property int $prescription_id
 * @property int $from_branch_id
 * @property int $to_branch_id
 * @property string $reason
 * @property int $created_by
 * @property CarbonImmutable|null $created_at
 */
class PrescriptionTransfer extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'prescription_id', 'from_branch_id', 'to_branch_id', 'reason', 'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Prescription, $this>
     */
    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
