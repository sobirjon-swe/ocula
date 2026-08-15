<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Actions\Defect;

use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Enums\DefectReason;
use App\Modules\Warehouse\Enums\MovementType;
use App\Modules\Warehouse\Models\Defect;
use App\Modules\Warehouse\Services\StockLedger;
use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Brakni qayd qilish — PROJECT.md 7.5, 7.20.
 *
 * "Usta faqat sababni tanlaydi, qolganini tizim qiladi": bu yerda
 * tizim ikki ishni qiladi — tovarni hisobdan chiqaradi va yo'qotilgan
 * qiymatni yozadi.
 *
 * Ombordagi tovar `defect` harakati bilan chiqadi va uning FIFO
 * tannarxi `cost_impact` ga tushadi. Mijozdan qaytgan brak esa
 * omborga umuman kirmagan bo'ladi — o'shanda harakat yozilmaydi
 * (`$fromStock = false`), lekin qiymat baribir yoziladi: zarar
 * ko'rilgan.
 *
 * Keyingi harakat sababdan kelib chiqadi (7.5) va u chaqiruvchida:
 * `customer_request` da buyurtma qayta ishlashga qaytadi,
 * `supplier_defect` da qaytarish akti tayyorlanadi (Finance bosqichi).
 */
final class ReportDefect
{
    public function __construct(private readonly StockLedger $ledger) {}

    /**
     * @param  array<string, mixed>  $links  `order_id`, `work_order_id`, `transfer_id`
     */
    public function handle(
        User $reporter,
        Location $location,
        int $variantId,
        int $quantity,
        DefectReason $reason,
        string $note,
        array $links = [],
        bool $fromStock = true,
        ?Money $costImpact = null,
        ?Model $document = null,
    ): Defect {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => __('warehouse::defect.quantity_must_be_positive'),
            ]);
        }

        return DB::transaction(function () use (
            $reporter, $location, $variantId, $quantity, $reason, $note, $links, $fromStock, $costImpact, $document
        ): Defect {
            $movementId = null;
            $cost = $costImpact ?? Money::zero();

            if ($fromStock) {
                $movement = $this->ledger->issue(
                    $reporter, $location, $variantId, $quantity,
                    MovementType::Defect, $document, $note,
                );

                $movementId = $movement->id;
                $cost = $movement->cost_total;
            }

            return Defect::create([
                'branch_id' => $location->branch_id,
                'location_id' => $location->id,
                'variant_id' => $variantId,
                'quantity' => $quantity,
                'reason' => $reason,
                'order_id' => $links['order_id'] ?? null,
                'work_order_id' => $links['work_order_id'] ?? null,
                'transfer_id' => $links['transfer_id'] ?? null,
                'cost_impact' => $cost->toString(),
                'movement_id' => $movementId,
                'photo_path' => $links['photo_path'] ?? null,
                'note' => $note,
                'reported_by' => $reporter->id,
            ]);
        });
    }
}
