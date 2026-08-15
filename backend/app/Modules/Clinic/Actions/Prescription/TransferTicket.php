<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Actions\Prescription;

use App\Modules\Clinic\Models\Prescription;
use App\Modules\Clinic\Models\PrescriptionTransfer;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tiketni boshqa filialga o'tkazish — PROJECT.md 7.11.
 *
 * 7.11 ning butun mohiyati tiketni **bitta filialda ushlab turish**,
 * shuning uchun bu amal qoidadan chekinish hisoblanadi va:
 *
 * - alohida ruxsat talab qiladi (`clinic.ticket.transfer_branch`);
 * - **sabab majburiy**;
 * - `prescription_transfers` ga yozib qo'yiladi.
 *
 * Yopilgan tiket ko'chirilmaydi: ish allaqachon bajarilgan, uni boshqa
 * filialga "o'tkazish" faqat chalkashlik keltirardi.
 */
final class TransferTicket
{
    public function handle(User $author, Prescription $prescription, int $toBranchId, string $reason): Prescription
    {
        if (! $prescription->ticket_active) {
            throw ValidationException::withMessages([
                'ticket' => __('clinic::prescription.ticket_not_active'),
            ]);
        }

        if ($prescription->ticket_branch_id === $toBranchId) {
            throw ValidationException::withMessages([
                'to_branch_id' => __('clinic::prescription.ticket_same_branch'),
            ]);
        }

        return DB::transaction(function () use ($author, $prescription, $toBranchId, $reason): Prescription {
            PrescriptionTransfer::create([
                'prescription_id' => $prescription->id,
                'from_branch_id' => $prescription->ticket_branch_id,
                'to_branch_id' => $toBranchId,
                'reason' => $reason,
                'created_by' => $author->id,
            ]);

            $prescription->update(['ticket_branch_id' => $toBranchId]);

            return $prescription;
        });
    }
}
