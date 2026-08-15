<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Actions\Visit;

use App\Modules\Clinic\Enums\VisitSource;
use App\Modules\Clinic\Enums\VisitStatus;
use App\Modules\Clinic\Models\Visit;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Navbatga qo'shish — PROJECT.md §6.5.
 *
 * Navbat raqami **filial va kun** kesimida beriladi: ertaga yana 1 dan
 * boshlanadi. Raqam takrorlanmasligi ikki qatlamda kafolatlanadi —
 * filial qatori tranzaksiya davomida qulflanadi, bazada esa
 * `unique(branch_id, queue_date, queue_number)` turadi.
 *
 * Qulf `document_sequences` dagi bilan bir xil mantiq (ANALIZ 3.12):
 * ikki sotuvchi bir vaqtda navbat berganda ikkalasi ham "7" raqamini
 * olmasligi kerak.
 *
 * Bitta mijozda bir kunda bitta ochiq vizit bo'ladi: ikki marta
 * navbatga qo'shilgan mijoz shifokor ro'yxatini ikkilantirardi.
 */
final class CreateVisit
{
    public function handle(
        User $author,
        Branch $branch,
        int $customerId,
        VisitSource $source = VisitSource::WalkIn,
        ?int $doctorId = null,
    ): Visit {
        return DB::transaction(function () use ($author, $branch, $customerId, $source, $doctorId): Visit {
            // Filial qatorini qulflaymiz — raqam shu qulf ostida beriladi.
            Branch::query()->whereKey($branch->id)->lockForUpdate()->firstOrFail();

            $date = now()->toDateString();

            $this->assertNotAlreadyInQueue($branch->id, $customerId, $date);

            return Visit::create([
                'branch_id' => $branch->id,
                'customer_id' => $customerId,
                'doctor_id' => $doctorId,
                'queue_number' => $this->nextNumber($branch->id, $date),
                'queue_date' => $date,
                'status' => VisitStatus::Waiting,
                'source' => $source,
                'created_by' => $author->id,
            ]);
        });
    }

    /**
     * Shu kundagi eng katta raqamdan keyingisi.
     */
    private function nextNumber(int $branchId, string $date): int
    {
        $last = Visit::query()
            ->withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->whereDate('queue_date', $date)
            ->max('queue_number');

        return (int) $last + 1;
    }

    private function assertNotAlreadyInQueue(int $branchId, int $customerId, string $date): void
    {
        $exists = Visit::query()
            ->withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('customer_id', $customerId)
            ->whereDate('queue_date', $date)
            ->open()
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'customer_id' => __('clinic::visit.already_in_queue'),
            ]);
        }
    }
}
