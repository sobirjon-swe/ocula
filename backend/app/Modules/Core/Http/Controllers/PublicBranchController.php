<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Core\Http\Resources\PublicBranchResource;
use App\Modules\Core\Models\Branch;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Filiallar — landing sayt uchun ochiq ro'yxat (manzillar) —
 * BOSQICH-11.md, PROJECT.md §11 (Bosqich 11).
 *
 * `auth:sanctum` dan tashqarida — hisobsiz mehmon uchun.
 */
final class PublicBranchController
{
    public function index(): AnonymousResourceCollection
    {
        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return PublicBranchResource::collection($branches);
    }
}
