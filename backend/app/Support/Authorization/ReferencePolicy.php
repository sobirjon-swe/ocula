<?php

declare(strict_types=1);

namespace App\Support\Authorization;

use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Ma'lumotnomalar uchun Policy asosi — brend, kategoriya, xizmat.
 *
 * Bu resurslarda PERMISSIONS.md CRUD ni bo'lmaydi, bitta `*.manage`
 * ruxsati beradi (§2). Ammo **o'qish** kengroq bo'lishi shart: sotuvchi
 * tovar kartochkasini to'ldirayotganda brend va kategoriya ro'yxatini
 * ko'rishi kerak, lekin ularni o'zgartira olmaydi.
 *
 * Shuning uchun ikki ruxsat: o'qish uchun `readPermission()`,
 * o'zgartirish uchun `managePermission()`.
 */
abstract class ReferencePolicy
{
    /**
     * O'zgartirish ruxsati — `catalog.brand.manage`, …
     */
    abstract protected function managePermission(): string;

    /**
     * Ro'yxatni ko'rish ruxsati — odatda modulning `view_any` i.
     */
    abstract protected function readPermission(): string;

    public function viewAny(User $user): bool
    {
        return $user->can($this->readPermission());
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can($this->readPermission());
    }

    public function create(User $user): bool
    {
        return $user->can($this->managePermission());
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can($this->managePermission());
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can($this->managePermission());
    }
}
