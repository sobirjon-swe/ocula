<?php

declare(strict_types=1);

namespace App\Support\Http;

use App\Modules\Sales\Models\Customer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mijoz kabineti kontrollerlarining umumiy asosi — BOSQICH-9.md §5 #1.
 *
 * `ApiController` qat'iy `App\Modules\Core\Models\User` (xodim) ga
 * bog'langan — ikkinchi guard uchun uni ikki marta yozish o'rniga
 * shu yupqa asos. Ruxsat tekshiruvi bu yerda Laravel Policy orqali
 * emas: har amalda bitta savol — "bu yozuv shu mijoznikimi?" — va u
 * to'g'ridan-to'g'ri controllerda, resursni `customer_id` bo'yicha
 * so'rab olish orqali javob topadi (begona yozuv 404, 403 emas).
 */
abstract class CustomerApiController
{
    protected function currentCustomer(Request $request): Customer
    {
        $customer = $request->user('customer');

        if (! $customer instanceof Customer) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        return $customer;
    }

    protected function perPage(Request $request, int $default = 25): int
    {
        $requested = $request->integer('per_page', $default);

        return max(1, min($requested, 100));
    }
}
