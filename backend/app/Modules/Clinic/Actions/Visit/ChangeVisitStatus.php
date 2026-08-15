<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Actions\Visit;

use App\Modules\Clinic\Enums\VisitStatus;
use App\Modules\Clinic\Models\Visit;
use App\Modules\Core\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Vizit holatini siljitish — ENUMS.md §5.
 *
 *     waiting → in_progress → finished
 *             → no_show | cancelled
 *
 * Ko'rikni **boshlagan shifokor** vizitga yoziladi: keyin "kim ko'rdi"
 * degan savolga javob qoladi va shifokor ko'rsatkichlari to'g'ri
 * chiqadi (7.14 — umumiy planshetda PIN bilan almashish shuning uchun).
 *
 * Boshlangan ko'rik bekor qilinmaydi — u yakunlanadi: aks holda
 * shifokor sarflagan vaqt hisobdan yo'qolardi.
 */
final class ChangeVisitStatus
{
    public function start(User $doctor, Visit $visit): Visit
    {
        if (! $visit->status->canBeStarted()) {
            throw ValidationException::withMessages([
                'status' => __('clinic::visit.not_startable', ['status' => $visit->status->value]),
            ]);
        }

        $visit->update([
            'status' => VisitStatus::InProgress,
            // Navbatga qo'shilganda shifokor noma'lum edi — endi ma'lum.
            'doctor_id' => $visit->doctor_id ?? $doctor->id,
            'started_at' => now(),
        ]);

        return $visit;
    }

    public function finish(Visit $visit): Visit
    {
        if (! $visit->status->canBeFinished()) {
            throw ValidationException::withMessages([
                'status' => __('clinic::visit.not_finishable', ['status' => $visit->status->value]),
            ]);
        }

        $visit->update([
            'status' => VisitStatus::Finished,
            'finished_at' => now(),
        ]);

        return $visit;
    }

    public function cancel(Visit $visit): Visit
    {
        return $this->abandon($visit, VisitStatus::Cancelled);
    }

    /**
     * Mijoz kelmadi — `cancelled` dan ataylab ajratilgan: hisobotda
     * shifokorning bo'sh o'tirgan vaqti ko'rinishi kerak.
     */
    public function markNoShow(Visit $visit): Visit
    {
        return $this->abandon($visit, VisitStatus::NoShow);
    }

    private function abandon(Visit $visit, VisitStatus $target): Visit
    {
        if (! $visit->status->canBeAbandoned()) {
            throw ValidationException::withMessages([
                'status' => __('clinic::visit.not_abandonable', ['status' => $visit->status->value]),
            ]);
        }

        $visit->update([
            'status' => $target,
            'finished_at' => now(),
        ]);

        return $visit;
    }
}
