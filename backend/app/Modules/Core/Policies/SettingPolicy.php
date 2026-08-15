<?php

declare(strict_types=1);

namespace App\Modules\Core\Policies;

use App\Modules\Core\Models\User;
use App\Support\Authorization\ResourcePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Sozlamalar — PERMISSIONS.md §1.
 *
 * Ko'rish `branch_manager` va `accountant` da ham bor (ular avans
 * foizi yoki yaxlitlash qadamini bilishi kerak), **o'zgartirish esa
 * faqat direktorda**: bu qiymatlar butun tarmoqning pul mantig'iga
 * ta'sir qiladi.
 *
 * Sozlama yaratilmaydi va o'chirilmaydi — kalitlar ro'yxati `SettingKey`
 * enumida qat'iy belgilangan, faqat qiymati o'zgaradi.
 *
 * Shu sababli asosiy tekshiruv `manage()` — u **yozuvsiz**, klass
 * darajasida chaqiriladi: ekran sozlamalarni bittalab emas, yaxlit
 * saqlaydi.
 */
final class SettingPolicy extends ResourcePolicy
{
    protected function permission(): string
    {
        return 'core.settings';
    }

    public function viewAny(User $user): bool
    {
        return $user->can('core.settings.view');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('core.settings.view');
    }

    /**
     * Sozlamalarni yaxlit o'zgartirish (yozuvsiz tekshiruv).
     */
    public function manage(User $user): bool
    {
        return $user->can('core.settings.update');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->manage($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
