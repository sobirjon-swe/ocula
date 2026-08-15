<?php

declare(strict_types=1);

namespace App\Modules\Core\Actions\Shift;

use App\Modules\Core\Enums\ShiftStatus;
use App\Modules\Core\Models\Shift;
use App\Modules\Core\Models\User;
use App\Support\Contracts\CashLedger;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Smena ochish — PROJECT.md 5.2, §15 #24.
 *
 * Filialda bir vaqtda faqat **bitta** ochiq smena bo'ladi. Buni bazadagi
 * partial unique indeks kafolatlaydi; bu yerdagi tekshiruv esa xodimga
 * tushunarli xabar berish uchun (§10).
 *
 * Boshlang'ich naqd kassa daftariga ham tushadi (`shift_opening`) —
 * shunda smena davomidagi har bir so'm daftarda izlanadi. Kassa
 * **interfeys orqali** chaqiriladi: Core moduli Finance'ga qaram
 * bo'lmasligi kerak.
 */
final class OpenShift
{
    public function __construct(private readonly CashLedger $cash) {}

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

            $shift = Shift::create([
                'branch_id' => $branchId,
                'opened_by' => $opener->id,
                'opened_at' => now(),
                'opening_cash' => $openingCash->toString(),
                'status' => ShiftStatus::Open,
            ]);

            $this->cash->recordShiftOpening($opener, $shift, $openingCash);

            return $shift;
        });
    }
}
