<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Http\Resources;

use App\Modules\Clinic\Models\Prescription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Retsept — SCHEMA.md §5, PROJECT.md 7.11.
 *
 * OD va OS alohida ob'ektlarda: shifokor ekrani ham, chek ham ularni
 * ikki ustun qilib ko'rsatadi (§10), tekis ro'yxat esa har safar
 * qayta guruhlashni talab qilardi.
 *
 * `is_expired` va `age_months` ataylab hisoblab beriladi: muddati
 * o'tgan retsept bo'yicha buyurtma **taqiqlanmaydi**, lekin sotuvchi
 * qayta ko'rikni tavsiya qilishi kerak (7.11).
 *
 * @mixin Prescription
 */
class PrescriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'visit_id' => $this->visit_id,
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'doctor_id' => $this->doctor_id,

            'od' => [
                'sph' => $this->od_sph,
                'cyl' => $this->od_cyl,
                'axis' => $this->od_axis,
                'add' => $this->od_add,
            ],
            'os' => [
                'sph' => $this->os_sph,
                'cyl' => $this->os_cyl,
                'axis' => $this->os_axis,
                'add' => $this->os_add,
            ],

            'pd' => $this->pd,
            'pd_near' => $this->pd_near,
            'prism' => $this->prism,
            'notes' => $this->notes,

            'valid_until' => $this->valid_until->toDateString(),
            'is_expired' => $this->isExpired(),
            'age_months' => $this->ageInMonths(),

            'ticket_active' => $this->ticket_active,
            'ticket_branch_id' => $this->ticket_branch_id,

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
