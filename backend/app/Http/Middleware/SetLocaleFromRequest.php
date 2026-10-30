<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Enums\Locale;
use App\Support\Text\Transliterator;
use Closure;
use Illuminate\Http\JsonResponse;
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

        $response = $next($request);
        if ($locale->isDerived() && $response instanceof JsonResponse) {
            $body = $response->getData(true);
            if (is_array($body)) {
                if (isset($body['message']) && is_string($body['message'])) {
                    $body['message'] = Transliterator::toCyrillic($body['message']);
                }
                if (isset($body['errors']) && is_array($body['errors'])) {
                    $body['errors'] = array_map(static fn ($errors) => is_array($errors)
                        ? array_map(static fn ($text) => is_string($text) ? Transliterator::toCyrillic($text) : $text, $errors)
                        : $errors, $body['errors']);
                }
                $response->setData($body);
            }
        }

        return $response;
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
            if ($userLocale instanceof Locale) {
                return $userLocale;
            }

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
