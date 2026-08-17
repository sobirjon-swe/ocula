<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Models;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Models\Order;
use App\Modules\Warehouse\Enums\DefectReason;
use App\Modules\Workshop\Models\WorkOrder;
use App\Support\Concerns\BelongsToBranch;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Carbon\CarbonImmutable;
use Database\Factories\DefectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Brak — SCHEMA.md §3, PROJECT.md 7.5.
 *
 * Sabab ikkita savolga javob beradi: **kim to'laydi** va **keyin nima
 * bo'ladi**. "Usta faqat sababni tanlaydi, qolganini tizim qiladi".
 *
 * `cost_impact` — yo'qotilgan qiymat (FIFO dan, 7.20). Bu raqamsiz
 * "brak qancha turdi" degan savolga javob bo'lmasdi va sabablar
 * bo'yicha taqqoslash ma'nosini yo'qotardi.
 *
 * @property int $id
 * @property int $branch_id
 * @property int $location_id
 * @property int $variant_id
 * @property int $quantity
 * @property DefectReason $reason
 * @property int|null $order_id
 * @property int|null $work_order_id
 * @property int|null $transfer_id
 * @property Money $cost_impact
 * @property int|null $movement_id
 * @property string|null $photo_path
 * @property string|null $note
 * @property int $reported_by
 * @property int|null $approved_by
 * @property CarbonImmutable|null $created_at
 */
class Defect extends Model
{
    /** @use HasFactory<DefectFactory> */
    use BelongsToBranch, HasFactory;

    protected $fillable = [
        'branch_id', 'location_id', 'variant_id', 'quantity', 'reason',
        'order_id', 'work_order_id', 'transfer_id', 'cost_impact',
        'movement_id', 'photo_path', 'note', 'reported_by', 'approved_by',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'cost_impact' => '0.00',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => DefectReason::class,
            'quantity' => 'integer',
            'cost_impact' => MoneyCast::class,
        ];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Brak qaysi ish buyrug'idan chiqqani — usta xatosini shu usta
     * hisobiga yozish uchun (7.12, `master_error`).
     *
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * Brakni hisobdan chiqargan harakat. Mijozdan qaytgan brakda
     * ombor tegilmaydi — o'shanda `null`.
     *
     * @return BelongsTo<StockMovement, $this>
     */
    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'movement_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * Do'kon zimmasidagi zarar — mukofot hisobiga ta'sir qiladi (7.12).
     *
     * @param  Builder<$this>  $query
     */
    public function scopeOnTheShop(Builder $query): void
    {
        $query->where('reason', DefectReason::MasterError->value);
    }
}
