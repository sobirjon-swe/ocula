<?php

declare(strict_types=1);

namespace App\Modules\Core\Actions\Shift;

use App\Modules\Core\Enums\ShiftStatus;
use App\Modules\Core\Models\Shift;
use App\Modules\Core\Models\User;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Smena ochish — PROJECT.md 5.2, §15 #24.
 *
 * Filialda bir vaqtda faqat **bitta** ochiq smena bo'ladi. Buni bazadagi
 * partial unique indeks kafolatlaydi; bu yerdagi tekshiruv esa xodimga
 * tushunarli xabar berish uchun (§10).
 */
final class OpenShift
{
    public function handle(User $opener, int $branchId, Money $openingCash): Shift
    {
        return DB::transaction(function () use ($opener, $branchId, $openingCash): Shift {
            $existing = Shift::withoutGlobalScopes()
                ->where('branch_id', $branchId)
                ->active()
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Shift) {
                throw ValidationException::withMessages([
                    'branch_id' => __('core::shift.already_open'),
                ]);
            }

            return Shift::create([
                'branch_id' => $branchId,
                'opened_by' => $opener->id,
                'opened_at' => now(),
                'opening_cash' => $openingCash->toString(),
                'status' => ShiftStatus::Open,
            ]);
        });
    }
}
