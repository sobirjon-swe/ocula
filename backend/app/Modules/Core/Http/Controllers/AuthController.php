<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Core\Actions\Auth\AuthenticateWithPassword;
use App\Modules\Core\Actions\Auth\AuthenticateWithPin;
use App\Modules\Core\Http\Requests\Auth\LoginRequest;
use App\Modules\Core\Http\Requests\Auth\PinLoginRequest;
use App\Modules\Core\Http\Resources\UserResource;
use App\Modules\Core\Models\User;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Xodim autentifikatsiyasi — PROJECT.md §9, 7.14.
 *
 * Ikki yo'l:
 * - kompyuterda telefon + parol;
 * - umumiy planshetda qurilma tokeni + 4 xonali PIN.
 *
 * Ikkalasi ham Sanctum tokeni qaytaradi. Mijoz (`customer`) alohida
 * guard bilan Bosqich 9 da qo'shiladi.
 */
final class AuthController extends ApiController
{
    public function login(LoginRequest $request, AuthenticateWithPassword $authenticate): JsonResponse
    {
        $user = $authenticate->handle(
            $request->string('phone')->toString(),
            $request->string('password')->toString(),
            $request->ip() ?? 'unknown',
        );

        return ApiResponse::data(
            $this->tokenPayload($request, $user, $request->string('device_name')->toString()),
        );
    }

    /**
     * Planshetda xodim almashishi — 7.14.
     *
     * Token nomi qurilma nomidan olinadi: direktor "qaysi planshetdan
     * kirilgan" ni ko'radi.
     */
    public function pin(PinLoginRequest $request, AuthenticateWithPin $authenticate): JsonResponse
    {
        [$user, $device] = $authenticate->handle(
            $request->integer('device_id'),
            $request->string('device_token')->toString(),
            $request->string('pin')->toString(),
        );

        $device->forceFill(['last_seen_at' => now()])->save();

        return ApiResponse::data($this->tokenPayload($request, $user, $device->name));
    }

    public function me(Request $request): UserResource
    {
        $user = $this->currentUser($request);
        $user->load(['branch', 'roles']);

        return (new UserResource($user))->additional([
            'meta' => ['permissions' => $user->getAllPermissions()->pluck('name')->all()],
        ]);
    }

    /**
     * Faqat **shu** qurilmaning tokeni bekor qilinadi — xodim boshqa
     * qurilmalarda kirgan holicha qoladi.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $this->currentUser($request)->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return ApiResponse::noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function tokenPayload(Request $request, User $user, string $deviceName): array
    {
        $user->forceFill(['last_login_at' => now()])->save();
        $user->load(['branch', 'roles']);

        return [
            'token' => $user->createToken($deviceName)->plainTextToken,
            'user' => (new UserResource($user))->resolve($request),
            'permissions' => $user->getAllPermissions()->pluck('name')->all(),
        ];
    }
}
