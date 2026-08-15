<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Policies;

use App\Modules\Core\Models\User;
use App\Support\Authorization\ResourcePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Vizit — PERMISSIONS.md §5.
 *
 * Navbatga qo'shish sotuvchida ham bor (u mijozni kutib oladi),
 * boshlash va yakunlash esa shifokorda: ko'rikni kim o'tkazgani
 * hujjatda qolishi kerak.
 *
 * Vizit **o'chirilmaydi** — bekor qilinadi yoki "kelmadi" deb
 * belgilanadi, aks holda shifokorning bo'sh o'tirgan vaqti hisobdan
 * yo'qolardi.
 */
final class VisitPolicy extends ResourcePolicy
{
    protected function permission(): string
    {
        return 'clinic.visit';
    }

    /**
     * Alohida `visit.view` ruxsati yo'q — navbatni ko'ra olgan
     * kartochkani ham ko'radi.
     */
    public function view(User $user, Model $model): bool
    {
        return $user->can('clinic.visit.view_any') && $this->withinBranch($user, $model);
    }

    public function start(User $user, Model $model): bool
    {
        return $user->can('clinic.visit.start') && $this->withinBranch($user, $model);
    }

    public function finish(User $user, Model $model): bool
    {
        return $user->can('clinic.visit.finish') && $this->withinBranch($user, $model);
    }

    public function cancel(User $user, Model $model): bool
    {
        return $user->can('clinic.visit.cancel') && $this->withinBranch($user, $model);
    }

    public function update(User $user, Model $model): bool
    {
        return false;
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
