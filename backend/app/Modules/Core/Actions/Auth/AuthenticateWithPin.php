<?php

declare(strict_types=1);

namespace App\Modules\Core\Actions\Auth;

use App\Modules\Core\Models\Device;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Umumiy planshetda PIN bilan almashish — PROJECT.md 7.14.
 *
 * PIN **qurilma doirasida** tekshiriladi: nomzodlar shu qurilma filialidagi,
 * qurilmaga ruxsat etilgan roldagi va PIN o'rnatgan faol xodimlar.
 * Shuning uchun 4 xonali PIN ikki filialda bir xil bo'lishi xavfsiz.
 *
 * Urinishlar **qurilma bo'yicha** cheklanadi — 4 xonali kodni terib
 * topish mumkin bo'lmasin.
 */
final class AuthenticateWithPin
{
    private const int MAX_ATTEMPTS = 5;

    private const int DECAY_SECONDS = 60;

    /**
     * @return array{0: User, 1: Device}
     */
    public function handle(int $deviceId, string $deviceToken, string $pin): array
    {
        $key = 'pin|device:'.$deviceId;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'pin' => __('core::auth.throttled', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ])->status(429);
        }

        $device = Device::query()
            ->where('id', $deviceId)
            ->where('is_active', true)
            ->first();

        if (! $device instanceof Device || ! Hash::check($deviceToken, $device->token)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'device_token' => __('core::auth.device_unknown'),
            ]);
        }

        $user = $this->matchPin($device, $pin);

        if (! $user instanceof User) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'pin' => __('core::auth.pin_failed'),
            ]);
        }

        RateLimiter::clear($key);

        return [$user, $device];
    }

    /**
     * Nomzodlarni birma-bir tekshiramiz: PIN noyob emas, hash esa
     * teskari qidirib bo'lmaydi. Bitta filialda PIN o'rnatgan xodim
     * o'nlab emas — narxi sezilarli emas.
     */
    private function matchPin(Device $device, string $pin): ?User
    {
        $candidates = User::query()
            ->where('branch_id', $device->branch_id)
            ->where('is_active', true)
            ->whereNotNull('pin_hash')
            ->when(
                $device->allowed_roles !== [],
                fn ($query) => $query->whereHas(
                    'roles',
                    fn ($roles) => $roles->whereIn('name', $device->allowed_roles),
                ),
            )
            ->get();

        foreach ($candidates as $candidate) {
            if ($candidate->pin_hash !== null && Hash::check($pin, $candidate->pin_hash)) {
                return $candidate;
            }
        }

        return null;
    }
}
