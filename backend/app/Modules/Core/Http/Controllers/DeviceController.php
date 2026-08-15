<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Core\Actions\Device\RegisterDevice;
use App\Modules\Core\Enums\DeviceType;
use App\Modules\Core\Http\Requests\Device\RegisterDeviceRequest;
use App\Modules\Core\Http\Requests\Device\UpdateDeviceRequest;
use App\Modules\Core\Http\Resources\DeviceResource;
use App\Modules\Core\Models\Device;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Qurilmalar — PROJECT.md 7.14.
 *
 * Planshet bir marta ro'yxatdan o'tadi, xodim esa 4 xonali PIN bilan
 * almashadi (`POST /auth/pin`). Shu sababli qurilma **o'chirilmaydi** —
 * tokeni bekor qilinadi, tarix esa qaysi planshetdan kirilganini
 * ko'rsatib turadi.
 *
 * Ro'yxat `BranchScope` bilan avtomatik cheklanadi.
 */
final class DeviceController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Device::class);

        $devices = QueryBuilder::for(Device::class)
            ->allowedFilters(
                AllowedFilter::exact('branch_id'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::partial('name'),
            )
            ->allowedSorts('name', 'created_at', 'last_seen_at')
            ->defaultSort('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return DeviceResource::collection($devices);
    }

    public function show(Device $device): DeviceResource
    {
        $this->authorize('view', $device);

        return new DeviceResource($device);
    }

    /**
     * Ro'yxatdan o'tkazish — token javobda **bir marta** qaytadi.
     *
     * Uni planshetga o'sha zahoti kiritish kerak: bazada faqat hash
     * qoladi, qayta ko'rsatib bo'lmaydi.
     */
    public function store(RegisterDeviceRequest $request, RegisterDevice $register): JsonResponse
    {
        $this->authorize('create', Device::class);

        $actor = $this->currentUser($request);
        $branchId = $request->integer('branch_id');

        if (! $actor->canAccessAllBranches() && ! in_array($branchId, $actor->accessibleBranchIds(), true)) {
            throw ValidationException::withMessages([
                'branch_id' => __('core::shift.foreign_branch'),
            ]);
        }

        /** @var array<int, string> $allowedRoles */
        $allowedRoles = array_map('strval', $request->array('allowed_roles'));

        [$device, $token] = $register->handle(
            $branchId,
            $request->string('name')->toString(),
            DeviceType::from($request->string('type')->toString()),
            $allowedRoles,
        );

        return ApiResponse::created([
            ...(new DeviceResource($device))->resolve($request),
            // Yagona joy va yagona payt — keyin faqat hash qoladi.
            'token' => $token,
        ]);
    }

    public function update(UpdateDeviceRequest $request, Device $device): DeviceResource
    {
        $this->authorize('update', $device);

        /** @var array<int, string> $allowedRoles */
        $allowedRoles = array_map('strval', $request->array('allowed_roles'));

        $device->update([
            'name' => $request->string('name')->toString(),
            'allowed_roles' => array_values(array_unique($allowedRoles)),
        ]);

        return new DeviceResource($device);
    }

    /**
     * Tokenni bekor qilish — planshet yo'qolsa yoki o'g'irlansa.
     *
     * `AuthenticateWithPin` faqat `is_active = true` qurilmani topadi,
     * shuning uchun bu bilan kirish darrov to'xtaydi.
     */
    public function revoke(Device $device): DeviceResource
    {
        $this->authorize('revoke', $device);

        $device->update(['is_active' => false]);

        return new DeviceResource($device);
    }
}
