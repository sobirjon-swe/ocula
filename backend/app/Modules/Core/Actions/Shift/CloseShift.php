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
 * Smena yopish — PROJECT.md 5.2.
 *
 * Sotuvchi seyfdagi **haqiqiy** naqdni kiritadi, tizim farqni hisoblaydi:
 * `difference = actual − expected`. Manfiy = kamomad.
 *
 * **Bosqich 1 cheklovi:** kutilgan naqd hozircha smena boshidagi
 * qoldiqqa teng — kassa harakatlari (`cash_movements`, `payments`)
 * Bosqich 3 da paydo bo'ladi. O'sha yerda `expectedCash()` shu
 * jadvallar bo'yicha hisoblanadigan qilib almashtiriladi.
 */
final class CloseShift
{
    public function handle(User $closer, Shift $shift, Money $actualCash, ?string $note = null): Shift
    {
        return DB::transaction(function () use ($closer, $shift, $actualCash, $note): Shift {
            $shift->refresh();

            if ($shift->status === ShiftStatus::Closed) {
                throw ValidationException::withMessages([
                    'shift' => __('core::shift.already_closed'),
                ]);
            }

            $expected = $this->expectedCash($shift);

            $shift->update([
                'closed_by' => $closer->id,
                'closed_at' => now(),
                'expected_cash' => $expected->toString(),
                'actual_cash' => $actualCash->toString(),
                'difference' => $actualCash->minus($expected)->toString(),
                'status' => ShiftStatus::Closed,
                'note' => $note,
            ]);

            return $shift;
        });
    }

    private function expectedCash(Shift $shift): Money
    {
        return $shift->opening_cash;
    }
}
