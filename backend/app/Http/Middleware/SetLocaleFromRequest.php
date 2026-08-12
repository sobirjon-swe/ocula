<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * So'rov tilini aniqlaydi — PROJECT.md §10.
 *
 * Ustuvorlik:
 * 1. `Accept-Language` sarlavhasi (SPA har so'rovda yuboradi);
 * 2. autentifikatsiyadan o'tgan foydalanuvchining `locale` ustuni;
 * 3. `uz-latn` (default).
 *
 * `uz-cyrl` alohida tarjima fayliga ega emas — u `uz-latn` dan
 * transliteratsiya qilinadi, shuning uchun Laravel lokali `uz-latn` ga
 * qo'yiladi, tanlangan til esa `app('request.locale')` da saqlanadi.
 */
final class SetLocaleFromRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        app()->setLocale($locale->sourceLocale()->value);
        app()->instance('request.locale', $locale);

        return $next($request);
    }

    private function resolve(Request $request): Locale
    {
        $header = $request->header('Accept-Language');

        if (is_string($header)) {
            $candidate = Locale::tryFrom(strtolower(trim(explode(',', $header)[0])));

            if ($candidate instanceof Locale) {
                return $candidate;
            }
        }

        $user = $request->user();

        if ($user !== null) {
            $userLocale = $user->getAttribute('locale');

            if (is_string($userLocale)) {
                $candidate = Locale::tryFrom($userLocale);

                if ($candidate instanceof Locale) {
                    return $candidate;
                }
            }
        }

        return Locale::default();
    }
}
