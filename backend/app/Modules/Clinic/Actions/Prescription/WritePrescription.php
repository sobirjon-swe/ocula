<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Actions\Prescription;

use App\Modules\Clinic\Models\Prescription;
use App\Modules\Clinic\Models\Visit;
use App\Modules\Core\Enums\SettingKey;
use App\Modules\Core\Models\Setting;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Retsept yozish — PROJECT.md §6.5, 7.11.
 *
 * Saqlangan zahoti **sotuvchida tiket ochiladi**: `ticket_active` va
 * `ticket_branch_id`. Tiket alohida jadval emas — retseptning o'zi ish
 * hujjati bo'lib turadi.
 *
 * Tiket **vizit bo'lgan filialda** ochiladi (7.11). Bu bosqichning eng
 * muhim qoidasi: A filialdagi shifokorning retsepti B filialga tushsa,
 * B da keraksiz ko'zoynak yasalib qoladi — sof brak va zarar.
 *
 * `valid_until` sozlamadan olinadi (default 12 oy, 7.11) — direktor uni
 * shifokor bilan kelishib o'zgartira oladi.
 */
final class WritePrescription
{
    /**
     * @param  array<string, mixed>  $values  OD/OS diopterlari, PD, prizma, izoh
     */
    public function handle(
        User $doctor,
        int $customerId,
        int $branchId,
        array $values,
        ?Visit $visit = null,
    ): Prescription {
        if ($visit instanceof Visit && $visit->customer_id !== $customerId) {
            throw ValidationException::withMessages([
                'visit_id' => __('clinic::prescription.visit_customer_mismatch'),
            ]);
        }

        return DB::transaction(function () use ($doctor, $customerId, $branchId, $values, $visit): Prescription {
            return Prescription::create([
                ...$values,
                'visit_id' => $visit?->id,
                'customer_id' => $customerId,
                'branch_id' => $branchId,
                'doctor_id' => $doctor->id,
                'valid_until' => $this->validUntil(),

                // Tiket shu yerda ochiladi — sotuvchi ekranida darrov
                // paydo bo'ladi.
                'ticket_active' => true,
                'ticket_branch_id' => $branchId,
            ]);
        });
    }

    /**
     * Mijozning oldingi retsepti — diopter sakrashini tekshirish uchun
     * (§10: "Yangi qiymat eskisidan 1.5+ farq qilsa — ogohlantirish").
     */
    public function previousFor(int $customerId, ?int $exceptId = null): ?Prescription
    {
        return Prescription::query()
            ->where('customer_id', $customerId)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->latest('created_at')
            ->latest('id')
            ->first();
    }

    private function validUntil(): string
    {
        $months = (int) Setting::valueFor(SettingKey::PrescriptionValidityMonths);

        return now()->addMonths($months)->toDateString();
    }
}
