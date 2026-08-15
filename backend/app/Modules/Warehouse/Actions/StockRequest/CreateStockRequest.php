<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Actions\StockRequest;

use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Enums\StockRequestStatus;
use App\Modules\Warehouse\Models\StockRequest;
use Illuminate\Validation\ValidationException;

/**
 * Ichki so'rov yaratish — SCHEMA.md §3.
 *
 * Sotuvchi mijoz oldida turib "bu bizda yo'q" degan holatni hujjatga
 * aylantiradi. Shu yozuvsiz transfer "kimdir shunchaki so'radi" bo'lib
 * qolardi va yo'qotilgan savdo hisoboti (7.9) ham to'liq chiqmasdi.
 */
final class CreateStockRequest
{
    public function handle(
        User $requester,
        int $fromBranchId,
        int $toBranchId,
        int $variantId,
        int $quantity,
        ?int $orderId = null,
    ): StockRequest {
        if ($fromBranchId === $toBranchId) {
            throw ValidationException::withMessages([
                'to_branch_id' => __('warehouse::request.same_branch'),
            ]);
        }

        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => __('warehouse::request.quantity_must_be_positive'),
            ]);
        }

        return StockRequest::create([
            'from_branch_id' => $fromBranchId,
            'to_branch_id' => $toBranchId,
            'variant_id' => $variantId,
            'quantity' => $quantity,
            'order_id' => $orderId,
            'status' => StockRequestStatus::Pending,
            'requested_by' => $requester->id,
        ]);
    }
}
