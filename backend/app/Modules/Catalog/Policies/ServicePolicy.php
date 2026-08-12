<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Policies;

use App\Support\Authorization\ReferencePolicy;

/**
 * Xizmat — PERMISSIONS.md §2 (`catalog.service.manage`).
 *
 * Xizmat narxi tovar narxidan farqli: u to'g'ridan-to'g'ri `services.price`
 * da turadi, `prices` jadvaliga tushmaydi (SCHEMA.md §2).
 */
final class ServicePolicy extends ReferencePolicy
{
    protected function managePermission(): string
    {
        return 'catalog.service.manage';
    }

    protected function readPermission(): string
    {
        return 'catalog.product.view_any';
    }
}
