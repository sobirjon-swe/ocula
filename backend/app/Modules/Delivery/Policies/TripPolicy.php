<?php

declare(strict_types=1);

namespace App\Modules\Delivery\Policies;

use App\Modules\Core\Models\User;
use App\Support\Authorization\ResourcePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Yo'l varaqasi — PERMISSIONS.md §7, BOSQICH-8.md §5 #5.
 *
 * `delivery.trip.view` (birlik) degan alohida ruxsat yo'q — xuddi
 * `OrderPolicy` dagi kabi, ro'yxatni ko'ra olgan kartochkani ham
 * ko'radi. `start`/`finish` — dispetcher (`delivery.trip.create`
 * egasi) istalgan reysga, oddiy haydovchi esa faqat o'zinikiga.
 */
final class TripPolicy extends ResourcePolicy
{
    protected function permission(): string
    {
        return 'delivery.trip';
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('delivery.trip.view_any');
    }

    public function start(User $user, Model $model): bool
    {
        return $user->can('delivery.trip.start') && $this->ownsOrDispatches($user, $model);
    }

    public function finish(User $user, Model $model): bool
    {
        return $user->can('delivery.trip.finish') && $this->ownsOrDispatches($user, $model);
    }

    public function cancel(User $user, Model $model): bool
    {
        return $user->can('delivery.trip.create');
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    /**
     * O'z reysi (haydovchi) yoki marshrut tuzuvchi (dispetcher) —
     * BOSQICH-8.md §5 #5.
     */
    private function ownsOrDispatches(User $user, Model $model): bool
    {
        return $user->can('delivery.trip.create') || (int) $model->getAttribute('driver_id') === $user->id;
    }
}
