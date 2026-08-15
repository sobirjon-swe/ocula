<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Actions\Transfer;

use App\Modules\Warehouse\Enums\TransferStatus;
use App\Modules\Warehouse\Models\Transfer;
use Illuminate\Validation\ValidationException;

/**
 * Transferni bekor qilish — ENUMS.md §3.
 *
 * Faqat qoralamadan: jo'natilgan tovar allaqachon ombordan chiqqan va
 * transitda turibdi. Uni "bekor qilish" qoldiqni havoda qoldirardi —
 * o'sha holatda tovar qaytarib jo'natiladi yoki qabul qilinib,
 * yetmagani hisobdan chiqariladi.
 */
final class CancelTransfer
{
    public function handle(Transfer $transfer): Transfer
    {
        if (! $transfer->status->canBeCancelled()) {
            throw ValidationException::withMessages([
                'status' => __('warehouse::transfer.cancel_only_draft'),
            ]);
        }

        $transfer->update(['status' => TransferStatus::Cancelled]);

        return $transfer;
    }
}
