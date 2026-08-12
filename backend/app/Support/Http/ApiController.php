<?php

declare(strict_types=1);

namespace App\Support\Http;

use App\Modules\Core\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Modul kontrollerlarining umumiy asosi — PROJECT.md §9.
 *
 * Biznes mantiq bu yerda ham, vorislarda ham yozilmaydi — u `Action`
 * yoki `Service` klassiga chiqadi. Bu yerda faqat HTTP qatlamiga
 * tegishli takrorlanuvchi mayda ishlar.
 */
abstract class ApiController
{
    use AuthorizesRequests;

    /**
     * `$request->user()` `Authenticatable` qaytaradi — modul kodiga
     * aniq `User` kerak. Guard o'tkazib yuborilgan bo'lsa (route'da
     * `auth:sanctum` yo'q) bu 401 bilan to'xtatadi.
     */
    protected function currentUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        return $user;
    }

    /**
     * Sahifa hajmi — `?per_page=50`. Yuqori chegara ataylab past:
     * filialdagi internet sekin, katta sahifa foyda bermaydi (§10).
     */
    protected function perPage(Request $request, int $default = 25): int
    {
        $requested = $request->integer('per_page', $default);

        return max(1, min($requested, 100));
    }
}
