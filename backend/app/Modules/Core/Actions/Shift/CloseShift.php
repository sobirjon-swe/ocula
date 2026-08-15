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
 * Smena yopish — PROJECT.md 5.2.
 *
 * Sotuvchi seyfdagi **haqiqiy** naqdni kiritadi, tizim farqni hisoblaydi:
 * `difference = actual − expected`. Manfiy = kamomad.
 *
 * Kutilgan naqd kassa daftaridan olinadi (`CashLedger::expectedCash()`),
 * so'ng farq bo'lsa daftarga `shortage`/`surplus` yoziladi — shunda
 * sanoqdan keyin daftar seyfdagi haqiqiy pul bilan mos keladi va
 * ertangi smena to'g'ri qoldiqdan boshlanadi.
 *
 * Tartib muhim: yozuv **hisobdan keyin** qo'yiladi, aks holda kamomad
 * o'zi kutilgan naqdni kamaytirib, farqni yeb yuborardi.
 */
final class CloseShift
{
    public function __construct(private readonly CashLedger $cash) {}

    public function handle(User $closer, Shift $shift, Money $actualCash, ?string $note = null): Shift
    {
        return DB::transaction(function () use ($closer, $shift, $actualCash, $note): Shift {
            $shift->refresh();

            if ($shift->status === ShiftStatus::Closed) {
                throw ValidationException::withMessages([
                    'shift' => __('core::shift.already_closed'),
                ]);
            }

            $expected = $this->cash->expectedCash($shift);
            $difference = $actualCash->minus($expected);

            $shift->update([
                'closed_by' => $closer->id,
                'closed_at' => now(),
                'expected_cash' => $expected->toString(),
                'actual_cash' => $actualCash->toString(),
                'difference' => $difference->toString(),
                'status' => ShiftStatus::Closed,
                'note' => $note,
            ]);

            $this->recordDifference($closer, $shift, $difference);

            return $shift;
        });
    }

    private function recordDifference(User $closer, Shift $shift, Money $difference): void
    {
        if ($difference->isZero()) {
            return;
        }

        if ($difference->isNegative()) {
            $this->cash->recordShortage($closer, $shift, $difference->absolute());

            return;
        }

        $this->cash->recordSurplus($closer, $shift, $difference);
    }
}
