<?php

declare(strict_types=1);

namespace App\Modules\Core\Actions\Auth;

use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Telefon + parol bilan kirish — SCHEMA.md §1.
 *
 * Xato urinishlar `telefon + IP` bo'yicha cheklanadi: bitta telefonni
 * turli IP dan urish ham, bitta IP dan turli telefonlarni urish ham
 * alohida hisoblanadi.
 *
 * Xato xabari **qaysi maydon noto'g'riligini aytmaydi** — mavjud
 * telefonlarni terib olishning oldi olinadi.
 */
final class AuthenticateWithPassword
{
    private const int MAX_ATTEMPTS = 5;

    private const int DECAY_SECONDS = 60;

    public function handle(string $phone, string $password, string $ip): User
    {
        $key = $this->throttleKey($phone, $ip);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'phone' => __('core::auth.throttled', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ])->status(429);
        }

        $user = User::query()->where('phone', $phone)->first();

        if (! $user instanceof User || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'phone' => __('core::auth.failed'),
            ]);
        }

        if (! $user->is_active) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'phone' => __('core::auth.inactive'),
            ]);
        }

        RateLimiter::clear($key);

        return $user;
    }

    private function throttleKey(string $phone, string $ip): string
    {
        return 'login|'.Str::lower($phone).'|'.$ip;
    }
}
