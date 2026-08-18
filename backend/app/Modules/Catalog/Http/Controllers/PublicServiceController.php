<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Resources\ServiceResource;
use App\Modules\Catalog\Models\Service;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Xizmat narxlari — landing sayt uchun ochiq ro'yxat (narxlar) —
 * BOSQICH-11.md, PROJECT.md §11 (Bosqich 11).
 *
 * `auth:sanctum` dan tashqarida — hisobsiz mehmon uchun. `ServiceResource`
 * qayta ishlatiladi: unda allaqachon faqat ochiq maydonlar bor.
 */
final class PublicServiceController
{
    public function index(): AnonymousResourceCollection
    {
        $services = Service::query()
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return ServiceResource::collection($services);
    }
}
