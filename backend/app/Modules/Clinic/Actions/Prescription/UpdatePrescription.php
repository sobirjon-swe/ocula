<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Actions\Prescription;

use App\Modules\Clinic\Models\Prescription;
use App\Modules\Core\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Retseptni tahrirlash — PERMISSIONS.md §5.
 *
 * Faqat **o'zi yozgan** shifokor va faqat **24 soat ichida**. Sabab
 * tibbiy javobgarlik: retsept bo'yicha ko'zoynak yasaladi, uni keyin
 * jimgina o'zgartirish yasalgan buyum bilan hujjatni ajratib
 * yuborardi. Kechroq xato topilsa — yangi retsept yoziladi.
 *
 * Tiketning holati bu yerda **o'zgarmaydi**: tahrir retseptning
 * mazmuniga tegadi, ish oqimiga emas.
 */
final class UpdatePrescription
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function handle(User $doctor, Prescription $prescription, array $values): Prescription
    {
        if ($prescription->doctor_id !== $doctor->id) {
            throw ValidationException::withMessages([
                'prescription' => __('clinic::prescription.not_own'),
            ]);
        }

        if ($this->windowClosed($prescription)) {
            throw ValidationException::withMessages([
                'prescription' => __('clinic::prescription.edit_window_closed', [
                    'hours' => Prescription::EDIT_WINDOW_HOURS,
                ]),
            ]);
        }

        $prescription->update($values);

        return $prescription;
    }

    private function windowClosed(Prescription $prescription): bool
    {
        $createdAt = $prescription->created_at;

        if ($createdAt === null) {
            return false;
        }

        return $createdAt->addHours(Prescription::EDIT_WINDOW_HOURS)->isPast();
    }
}
