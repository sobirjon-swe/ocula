<?php

declare(strict_types=1);

namespace App\Modules\Clinic;

use App\Support\Providers\ModuleServiceProvider;

/**
 * Clinic — vizit, navbat, retsept.
 *
 * PROJECT.md §6.5, 7.11 (retsept qamrovi FILIAL BO'YICHA cheklangan —
 * kritik qoida: boshqa filialda keraksiz ko'zoynak yasalib qolmasin).
 */
final class ClinicServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Clinic';
    }
}
