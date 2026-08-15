<?php

declare(strict_types=1);

namespace App\Modules\Core\Policies;

use App\Modules\Core\Models\User;
use App\Support\Authorization\ResourcePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Qurilma — PERMISSIONS.md §1, PROJECT.md 7.14.
 *
 * Matritsa bo'yicha `core.device.*` `director` va `branch_manager` da.
 * Ro'yxatdan o'tkazish va bekor qilish alohida ruxsatlar: token berish
 * bilan uni tortib olish bir xil mas'uliyat emas.
 *
 * Qurilma **o'chirilmaydi** — tokeni bekor qilinadi (`is_active`).
 * O'chirilsa PIN bilan kirish tarixi qaysi planshetdan bo'lgani
 * ko'rinmay qolardi.
 */
final class DevicePolicy extends ResourcePolicy
{
    protected function permission(): string
    {
        return 'core.device';
    }

    /**
     * Alohida `device.view` ruxsati yo'q — ro'yxatni ko'ra olgan
     * kartochkani ham ko'radi.
     */
    public function view(User $user, Model $model): bool
    {
        return $user->can('core.device.view_any') && $this->withinBranch($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->can('core.device.register');
    }

    public function revoke(User $user, Model $model): bool
    {
        return $user->can('core.device.revoke') && $this->withinBranch($user, $model);
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('core.device.register') && $this->withinBranch($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
