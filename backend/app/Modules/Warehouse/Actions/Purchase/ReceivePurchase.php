<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Actions\Purchase;

use App\Modules\Core\Models\User;
use App\Modules\Finance\Services\SupplierLedger;
use App\Modules\Warehouse\Enums\MovementType;
use App\Modules\Warehouse\Enums\PurchaseStatus;
use App\Modules\Warehouse\Models\Purchase;
use App\Modules\Warehouse\Services\StockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kirim hujjatini qabul qilish — PROJECT.md 7.20, SCHEMA.md §3, 7.15.
 *
 * Shu paytgacha ombor **tegilmagan** edi: `draft` hujjat faqat qog'oz.
 * Qabul qilinganda har bir satr uchun alohida FIFO qatlami ochiladi va
 * `unit_cost` sifatida satrning `cost_price` i olinadi.
 *
 * Butun hujjat bitta tranzaksiyada: bir satr o'tib, ikkinchisi
 * o'tmasligi — qoldiq bilan hujjat ajralib qolishi degani. Shu
 * tranzaksiyada yetkazib beruvchi hisobiga ham qarz yoziladi (7.15) —
 * tovar keldi, hali to'lanmadi.
 *
 * `received` dan orqaga qaytish yo'q (ENUMS.md §3) — xato bo'lsa
 * har bir harakat alohida storno qilinadi (7.21).
 */
final class ReceivePurchase
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly SupplierLedger $supplierLedger,
    ) {}

    public function handle(User $receiver, Purchase $purchase): Purchase
    {
        if ($purchase->status !== PurchaseStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => __('warehouse::purchase.not_draft'),
            ]);
        }

        $items = $purchase->items()->get();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => __('warehouse::purchase.no_items'),
            ]);
        }

        return DB::transaction(function () use ($receiver, $purchase, $items): Purchase {
            $location = $purchase->location()->firstOrFail();

            foreach ($items as $item) {
                $this->ledger->receive(
                    $receiver,
                    $location,
                    $item->variant_id,
                    $item->quantity,
                    $item->cost_price,
                    MovementType::Purchase,
                    $purchase,
                );
            }

            $purchase->update([
                'status' => PurchaseStatus::Received,
                'received_at' => now(),
                'received_by' => $receiver->id,
            ]);

            $this->supplierLedger->recordPurchase($receiver, $purchase->supplier()->firstOrFail(), $purchase);

            return $purchase;
        });
    }
}
