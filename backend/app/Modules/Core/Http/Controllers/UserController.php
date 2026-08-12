<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Core\Http\Requests\User\AssignRolesRequest;
use App\Modules\Core\Http\Requests\User\SetDebtLimitRequest;
use App\Modules\Core\Http\Requests\User\SetPinRequest;
use App\Modules\Core\Http\Requests\User\StoreUserRequest;
use App\Modules\Core\Http\Requests\User\UpdateUserRequest;
use App\Modules\Core\Http\Resources\UserResource;
use App\Modules\Core\Models\User;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Xodimlar CRUD — PROJECT.md §6.1, PERMISSIONS.md §1.
 *
 * Rol, qarz limiti va PIN uchun **alohida endpoint** — har biri alohida
 * ruxsat talab qiladi, shuning uchun ularni umumiy `update` ga qo'shish
 * ruxsat modelini buzardi.
 *
 * `users` jadvalida `BranchScope` yo'q (xodim filialsiz ham bo'ladi),
 * shuning uchun ro'yxat filialga qo'lda cheklanadi.
 */
final class UserController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $actor = $this->currentUser($request);

        $users = QueryBuilder::for(User::class)
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::partial('phone'),
                AllowedFilter::exact('branch_id'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::callback(
                    'role',
                    fn ($query, $value) => $query->whereHas(
                        'roles',
                        fn ($roles) => $roles->whereIn('name', (array) $value),
                    ),
                ),
            )
            ->allowedSorts('name', 'created_at', 'last_login_at')
            ->defaultSort('name')
            ->unless(
                $actor->canAccessAllBranches(),
                fn ($query) => $query->whereIn('branch_id', $actor->accessibleBranchIds()),
            )
            ->with(['branch', 'roles'])
            ->paginate($this->perPage($request))
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validated();
        $roles = $data['roles'] ?? [];
        unset($data['roles']);

        $user = User::create($data);

        if ($roles !== []) {
            $this->authorize('assignRole', $user);
            $user->syncRoles($roles);
        }

        $user->load(['branch', 'roles']);

        return ApiResponse::created((new UserResource($user))->resolve($request));
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user->load(['branch', 'roles']));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        $user->update($request->validated());

        return new UserResource($user->load(['branch', 'roles']));
    }

    /**
     * O'chirish = **faolsizlantirish** (PERMISSIONS.md §1).
     *
     * Xodim yozuvi tarixda qoladi: uning nomi hujjatlarda va mukofot
     * hisobida ko'rinib turishi kerak.
     */
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $user->update(['is_active' => false]);
        $user->tokens()->delete();

        return ApiResponse::noContent();
    }

    public function assignRoles(AssignRolesRequest $request, User $user): UserResource
    {
        $this->authorize('assignRole', $user);

        /** @var array<int, string> $roles */
        $roles = $request->validated()['roles'];
        $user->syncRoles($roles);

        return new UserResource($user->load(['branch', 'roles']));
    }

    public function setDebtLimit(SetDebtLimitRequest $request, User $user): UserResource
    {
        $this->authorize('setDebtLimit', $user);

        $user->update([
            'debt_limit' => Money::of($request->string('debt_limit')->toString())->toString(),
        ]);

        return new UserResource($user->load(['branch', 'roles']));
    }

    /**
     * PIN o'rnatish/olib tashlash — 7.14.
     *
     * PIN hech qachon qaytarilmaydi: javobda faqat `has_pin` bayrog'i.
     */
    public function setPin(SetPinRequest $request, User $user): UserResource
    {
        $this->authorize('setPin', $user);

        $pin = $request->validated()['pin'] ?? null;

        $user->forceFill([
            'pin_hash' => $pin,
            'pin_set_at' => $pin === null ? null : now(),
        ])->save();

        return new UserResource($user->load(['branch', 'roles']));
    }
}
