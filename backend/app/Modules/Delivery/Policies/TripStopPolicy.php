<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Delivery\Models\TripStop;
use App\Support\Authorization\ResourcePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * To'xtash — PERMISSIONS.md §7, BOSQICH-8.md §5 #4.
 *
 * PERMISSIONS.md so'zma-so'z aytmaydi, lekin nuance #6
 * (`delivery.balance.view` — "faqat o'ziniki") bilan bir xil mantiq:
 * haydovchi faqat **o'z reysidagi** to'xtashni yetkazadi/muvaffaqiyatsiz
 * deb belgilaydi, aks holda boshqa haydovchining ishini bajargan
 * bo'lib qolardi.
 */
final class TripStopPolicy extends ResourcePolicy
{
    protected function permission(): string
    {
        return 'delivery.stop';
    }

    public function deliver(User $user, Model $model): bool
    {
        return $user->can('delivery.stop.deliver') && $this->ownTrip($user, $model);
    }

    public function fail(User $user, Model $model): bool
    {
        return $user->can('delivery.stop.fail') && $this->ownTrip($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    private function ownTrip(User $user, Model $model): bool
    {
        /** @var TripStop $model */
        return $model->trip->driver_id === $user->id;
    }
}
