<?php

declare(strict_types=1);

namespace App\Modules\Core;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Device;
use App\Modules\Core\Models\Setting;
use App\Modules\Core\Models\Shift;
use App\Modules\Core\Models\User;
use App\Modules\Core\Policies\BranchPolicy;
use App\Modules\Core\Policies\DevicePolicy;
use App\Modules\Core\Policies\SettingPolicy;
use App\Modules\Core\Policies\ShiftPolicy;
use App\Modules\Core\Policies\UserPolicy;
use App\Support\Providers\ModuleServiceProvider;

/**
 * Core — filiallar, xodimlar, rollar, smenalar, qurilmalar, sozlamalar.
 *
 * PROJECT.md §6.1. Boshqa barcha modullar shunga tayanadi:
 * `Branch`, `Location`, `Shift`, `Device` shu yerda yashaydi.
 */
final class CoreServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Core';
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [
            Branch::class => BranchPolicy::class,
            User::class => UserPolicy::class,
            Shift::class => ShiftPolicy::class,
            Device::class => DevicePolicy::class,
            Setting::class => SettingPolicy::class,
        ];
    }
}
