<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Policies;

use App\Support\Authorization\ReferencePolicy;

/**
 * Brend — PERMISSIONS.md §2 (`catalog.brand.manage`, faqat direktorda).
 */
final class BrandPolicy extends ReferencePolicy
{
    protected function managePermission(): string
    {
        return 'catalog.brand.manage';
    }

    protected function readPermission(): string
    {
        return 'catalog.product.view_any';
    }
}
