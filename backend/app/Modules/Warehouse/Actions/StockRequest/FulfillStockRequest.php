<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Actions\StockRequest;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Actions\Transfer\CreateTransfer;
use App\Modules\Warehouse\Enums\StockRequestStatus;
use App\Modules\Warehouse\Models\StockRequest;
use App\Modules\Warehouse\Models\Transfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tasdiqlangan so'rovdan transfer yaratish — PROJECT.md §11 (Bosqich 4).
 *
 * So'rov va transfer bir-biriga bog'lanadi (`stock_requests.transfer_id`),
 * shunda "nima uchun bu tovar yo'lga chiqdi" degan savolga javob
 * qoladi va yo'nalish bo'yicha statistika yig'iladi.
 *
 * Transfer **qoralama** bo'lib tug'iladi: jo'natish alohida amal va
 * alohida ruxsat (`warehouse.transfer.send`) talab qiladi.
 */
final class FulfillStockRequest
{
    public function __construct(private readonly CreateTransfer $createTransfer) {}

    public function handle(User $author, StockRequest $request): Transfer
    {
        if (! $request->status->canBeFulfilled()) {
            throw ValidationException::withMessages([
                'status' => __('warehouse::request.not_approved'),
            ]);
        }

        return DB::transaction(function () use ($author, $request): Transfer {
            // Tovar so'ralayotgan filialdan chiqadi va so'ragan filialga
            // boradi — yo'nalish so'rovga nisbatan teskari.
            $from = $this->warehouseOf($request->to_branch_id);
            $to = $this->warehouseOf($request->from_branch_id);

            $transfer = $this->createTransfer->handle(
                $author,
                $from,
                $to,
                [['variant_id' => $request->variant_id, 'quantity' => $request->quantity]],
                __('warehouse::request.transfer_note', ['id' => $request->id]),
            );

            $request->update([
                'status' => StockRequestStatus::Fulfilled,
                'transfer_id' => $transfer->id,
            ]);

            return $transfer;
        });
    }

    /**
     * 1-versiyada har filialda bitta ombor bo'ladi (§15 #22).
     */
    private function warehouseOf(int $branchId): Location
    {
        $branch = Branch::query()->findOrFail($branchId);
        $location = $branch->warehouse();

        if (! $location instanceof Location) {
            throw ValidationException::withMessages([
                'branch_id' => __('warehouse::transfer.branch_has_no_warehouse', [
                    'name' => $branch->name,
                ]),
            ]);
        }

        return $location;
    }
}
