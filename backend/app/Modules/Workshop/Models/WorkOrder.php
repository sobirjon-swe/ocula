<?php

declare(strict_types=1);

namespace App\Modules\Workshop\Models;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Models\Order;
use App\Modules\Workshop\Enums\WorkOrderPriority;
use App\Modules\Workshop\Enums\WorkOrderStatus;
use App\Support\Concerns\BelongsToBranch;
use Carbon\CarbonImmutable;
use Database\Factories\WorkOrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ustaxona ish buyrug'i — SCHEMA.md §6, PROJECT.md §6.6.
 *
 * Buyurtmaning **ustaxonadagi qismi**. Uning holati buyurtma
 * holatidan alohida o'q (7.3 bilan bir mantiq): ustaxonada ish
 * tugagani buyurtma mijozga topshirilgani degani emas.
 *
 * Qo'lda yaratilmaydi — buyurtma `in_workshop` ga o'tganda tizim
 * tug'diradi, shuning uchun PERMISSIONS.md §6 da `create` yo'q.
 *
 * @property int $id
 * @property int $order_id
 * @property int $branch_id
 * @property int|null $master_id
 * @property WorkOrderStatus $status
 * @property WorkOrderPriority $priority
 * @property CarbonImmutable|null $due_at
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property int $rework_count
 * @property string|null $note
 * @property int $created_by
 * @property CarbonImmutable|null $created_at
 */
class WorkOrder extends Model
{
    /** @use HasFactory<WorkOrderFactory> */
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'order_id', 'branch_id', 'master_id', 'status', 'priority',
        'due_at', 'started_at', 'finished_at', 'rework_count', 'note', 'created_by',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'queued',
        'priority' => 'normal',
        'rework_count' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkOrderStatus::class,
            'priority' => WorkOrderPriority::class,
            'rework_count' => 'integer',
            'due_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function master(): BelongsTo
    {
        return $this->belongsTo(User::class, 'master_id');
    }

    /**
     * @return HasMany<WorkOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(WorkOrderItem::class);
    }

    /**
     * Muddati o'tganmi — kanbanda qizil (§10).
     */
    public function isOverdue(): bool
    {
        return $this->due_at !== null
            && $this->status->isOpen()
            && $this->due_at->isPast();
    }

    /**
     * Kanbandagi ochiq ustunlar (§10: Yangi → Ishda → Tayyor).
     *
     * @param  Builder<$this>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [
            WorkOrderStatus::Queued->value,
            WorkOrderStatus::InProgress->value,
        ]);
    }

    /**
     * Shoshilinch ish tepada — usta ro'yxatning oxiridagi kechikkan
     * buyurtmani ko'rmay qolmasin.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeByUrgency(Builder $query): void
    {
        $query
            ->orderByRaw("CASE priority
                WHEN 'urgent' THEN 4
                WHEN 'high' THEN 3
                WHEN 'normal' THEN 2
                ELSE 1 END DESC")
            ->orderByRaw('due_at IS NULL')
            ->orderBy('due_at')
            ->orderBy('id');
    }
}
