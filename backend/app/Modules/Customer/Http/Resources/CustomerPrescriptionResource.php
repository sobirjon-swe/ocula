<?php

declare(strict_types=1);

namespace App\Modules\Customer\Http\Resources;

use App\Modules\Clinic\Models\Prescription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mijozning o'z retsepti — PROJECT.md §6.9, §10 ("saqlab olsa
 * bo'ladigan chiroyli karta"), PERMISSIONS.md §12.
 *
 * `ticket_active`/`ticket_branch_id` ataylab yo'q — bu xodim ishi
 * (7.11), mijozga tegishli emas.
 *
 * @mixin Prescription
 */
class CustomerPrescriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch' => $this->whenLoaded('branch', fn () => $this->branch->name),
            'doctor' => $this->whenLoaded('doctor', fn () => $this->doctor->name),
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
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
