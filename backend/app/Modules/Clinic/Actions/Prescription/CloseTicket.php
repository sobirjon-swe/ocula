<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Actions\Prescription;

use App\Modules\Clinic\Models\Prescription;

/**
 * Tiketni yopish — PROJECT.md 7.11.
 *
 * Tiket **buyurtma ochilganda** yopiladi: uning butun vazifasi
 * sotuvchiga "bu retsept bo'yicha ish bor" deb turish edi, buyurtma
 * ochilgach vazifa bajarildi.
 *
 * Yopish faqat **o'z filialining** tiketiga tegadi. Mijoz boshqa
 * filialga kelib, eski retsepti bo'yicha buyurtma bersa (7.11 buni
 * ataylab ruxsat beradi), asl filialdagi tiket **ochiq qoladi**: u
 * yerdagi ish hali bajarilmagan va uni bu buyurtma yopmaydi.
 */
final class CloseTicket
{
    public function handle(Prescription $prescription, int $branchId): bool
    {
        if (! $prescription->ticket_active || $prescription->ticket_branch_id !== $branchId) {
            return false;
        }

        $prescription->update(['ticket_active' => false]);

        return true;
    }
}
