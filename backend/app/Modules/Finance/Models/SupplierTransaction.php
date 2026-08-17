<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\SupplierTxType;
use App\Modules\Warehouse\Models\Purchase;
use App\Modules\Warehouse\Models\Supplier;
use App\Support\Concerns\Immutable;
use App\Support\Money\Money;
use App\Support\Money\MoneyCast;
use Carbon\CarbonImmutable;
use Database\Factories\SupplierTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Yetkazib beruvchi bilan ikki tomonlama hisob — SCHEMA.md, PROJECT.md
 * 7.15, BOSQICH-10.md §10a.
 *
 * Insert-only (7.21): balans shu yozuvlar ishorali yig'indisi.
 * `amount` musbat = to'lov (qarzimiz kamayadi), manfiy = kirim
 * (qarzimiz oshadi) — `SupplierLedger` yagona yozuvchi joy.
 *
 * @property int $id
 * @property int $supplier_id
 * @property SupplierTxType $type
 * @property Money $amount
 * @property int|null $purchase_id
 * @property CarbonImmutable|null $due_date
 * @property int|null $reverses_id
 * @property string|null $reason
 * @property int $created_by
 * @property CarbonImmutable $created_at
 */
class SupplierTransaction extends Model
{
    /** @use HasFactory<SupplierTransactionFactory> */
    use HasFactory, Immutable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'supplier_id', 'type', 'amount', 'purchase_id', 'due_date',
        'reverses_id', 'reason', 'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SupplierTxType::class,
            'amount' => MoneyCast::class,
            'due_date' => 'date',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<SupplierTransaction, $this>
     */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }
}
