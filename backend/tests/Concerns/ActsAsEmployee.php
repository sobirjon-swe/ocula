<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use Database\Seeders\RolePermissionSeeder;

/**
 * Rol va filial bilan xodim yasab, uning nomidan so'rov yuborish.
 *
 * `RolePermissionSeeder` siz ruxsatlar bo'lmaydi — Policy hamma narsani
 * rad etadi va testlar sababini aytmaydigan 403 bilan yiqiladi.
 */
trait ActsAsEmployee
{
    protected function seedPermissions(): void
    {
        $this->seed(RolePermissionSeeder::class);
    }

    protected function employee(Role $role, ?Branch $branch = null): User
    {
        $user = User::factory()
            ->when($branch !== null, fn ($factory) => $factory->for($branch))
            ->create($branch === null ? ['branch_id' => null] : []);

        $user->assignRole($role->value);

        return $user;
    }

    /**
     * Direktorda filial yo'q — u butun tarmoqni ko'radi (§4).
     */
    protected function actingAsDirector(): User
    {
        $director = $this->employee(Role::Director);
        $this->actingAs($director, 'sanctum');

        return $director;
    }

    protected function actingAsEmployee(Role $role, ?Branch $branch = null): User
    {
        $user = $this->employee($role, $branch);
        $this->actingAs($user, 'sanctum');

        return $user;
    }
}
