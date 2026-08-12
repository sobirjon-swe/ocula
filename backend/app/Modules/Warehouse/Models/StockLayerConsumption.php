<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Models;

use App\Support\Concerns\Immutable;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Qatlam sarflashi — PROJECT.md 7.20, 7.21.
 *
 * `stock_movements.cost_total` = shu yozuvlardagi `total_cost` yig'indisi.
 * Storno qilinganda **manfiy** yozuv qo'shiladi, asl yozuv o'chirilmaydi.
 *
 * @property int $id
 * @property int $layer_id
 * @property int $movement_id
 * @property int $quantity
 * @property Money $unit_cost
 * @property Money $total_cost
 * @property CarbonImmutable|null $created_at
 */
class StockLayerConsumption extends Model
{
    use Immutable;

    public const UPDATED_AT = null;

    protected $fillable = ['layer_id', 'movement_id', 'quantity', 'unit_cost', 'total_cost'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => MoneyCast::class,
            'total_cost' => MoneyCast::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<StockLayer, $this>
     */
    public function layer(): BelongsTo
    {
        return $this->belongsTo(StockLayer::class, 'layer_id');
    }

    /**
     * @return BelongsTo<StockMovement, $this>
     */
    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'movement_id');
    }
}
