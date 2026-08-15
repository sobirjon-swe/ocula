<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Models;

use App\Modules\Catalog\Models\ProductVariant;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transfer satri — SCHEMA.md §3.
 *
 * `qty_received` qabul qilinmaguncha `null`: "hali sanalmagan" bilan
 * "nol dona keldi" bir xil narsa emas — birinchisi jarayon, ikkinchisi
 * nomuvofiqlik (7.4).
 *
 * `unit_cost` jo'natish paytida FIFO dan hisoblanadi va yo'l davomida
 * o'zgarmaydi: tovar boshqa filialga **o'sha tannarx bilan** kiradi,
 * aks holda transferning o'zi foyda yoki zarar yasab qo'yardi.
 *
 * @property int $id
 * @property int $transfer_id
 * @property int $variant_id
 * @property int $qty_sent
 * @property int|null $qty_received
 * @property Money|null $unit_cost
 */
class TransferItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'transfer_id', 'variant_id', 'qty_sent', 'qty_received', 'unit_cost',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty_sent' => 'integer',
            'qty_received' => 'integer',
            'unit_cost' => MoneyCast::class,
        ];
    }

    /**
     * @return BelongsTo<Transfer, $this>
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * Yetmagan miqdor — qabul qilingandan keyin ma'lum bo'ladi.
     */
    public function missingQuantity(): int
    {
        return $this->qty_received === null ? 0 : max(0, $this->qty_sent - $this->qty_received);
    }
}
