<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Models;

use App\Modules\Clinic\Enums\VisitSource;
use App\Modules\Clinic\Enums\VisitStatus;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Models\Customer;
use App\Support\Concerns\BelongsToBranch;
use Carbon\CarbonImmutable;
use Database\Factories\VisitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Ko'rik viziti — SCHEMA.md §5, PROJECT.md §6.5.
 *
 * Navbat raqami filial va kun kesimida beriladi (`queue_date`),
 * ertaga yana 1 dan boshlanadi.
 *
 * Vizit **filialga bog'langan** — `BelongsToBranch` odatdagidek
 * ishlaydi: boshqa filial navbatini ko'rishning ma'nosi yo'q.
 *
 * @property int $id
 * @property int $branch_id
 * @property int $customer_id
 * @property int|null $doctor_id
 * @property int $queue_number
 * @property CarbonImmutable|null $queue_date
 * @property VisitStatus $status
 * @property VisitSource $source
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 */
class Visit extends Model
{
    /** @use HasFactory<VisitFactory> */
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id', 'customer_id', 'doctor_id', 'queue_number', 'queue_date',
        'status', 'source', 'started_at', 'finished_at', 'created_by',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'waiting',
        'source' => 'walk_in',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VisitStatus::class,
            'source' => VisitSource::class,
            'queue_number' => 'integer',
            'queue_date' => 'date',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
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
     * @return BelongsTo<User, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    /**
     * Vizit yakunida yozilgan retsept (bo'lsa).
     *
     * @return HasOne<Prescription, $this>
     */
    public function prescription(): HasOne
    {
        return $this->hasOne(Prescription::class);
    }

    /**
     * Shifokor ekranidagi jonli navbat (§6.5).
     *
     * @param  Builder<$this>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [
            VisitStatus::Waiting->value,
            VisitStatus::InProgress->value,
        ]);
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeForDate(Builder $query, string $date): void
    {
        $query->whereDate('queue_date', $date);
    }
}
