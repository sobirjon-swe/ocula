<?php

declare(strict_types=1);

namespace App\Modules\Clinic;

use App\Modules\Clinic\Models\Prescription;
use App\Modules\Clinic\Models\Visit;
use App\Modules\Clinic\Policies\PrescriptionPolicy;
use App\Modules\Clinic\Policies\VisitPolicy;
use App\Support\Providers\ModuleServiceProvider;

/**
 * Clinic — vizit, navbat, retsept, tiket.
 *
 * PROJECT.md §6.5, 7.11 (retsept qamrovi filial bo'yicha cheklangan),
 * 7.14 (umumiy planshetda PIN bilan almashish).
 */
final class ClinicServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Clinic';
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [
            Visit::class => VisitPolicy::class,
            Prescription::class => PrescriptionPolicy::class,
        ];
    }
}
