<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Policies;

use App\Support\Authorization\ReferencePolicy;

/**
 * Kategoriya — PERMISSIONS.md §2 (`catalog.category.manage`).
 */
final class CategoryPolicy extends ReferencePolicy
{
    protected function managePermission(): string
    {
        return 'catalog.category.manage';
    }

    protected function readPermission(): string
    {
        return 'catalog.product.view_any';
    }
}
