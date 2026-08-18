<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Policies;

use App\Modules\Core\Models\User;

/**
 * Onlayn navbat so'rovi — BOSQICH-11.md.
 *
 * `store` bu yerda yo'q — landing saytdan hisobsiz yoziladi, `auth:sanctum`
 * dan tashqarida (PublicAppointmentController).
 */
final class AppointmentRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clinic.appointment_request.view_any');
    }

    public function manage(User $user): bool
    {
        return $user->can('clinic.appointment_request.manage');
    }
}
