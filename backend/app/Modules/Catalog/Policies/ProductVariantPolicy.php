<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Policies;

use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Variant — PERMISSIONS.md §2.
 *
 * Variantda o'chirish ruxsati **umuman yo'q**: variant ombor harakati,
 * narx tarixi va sotuv tarixining ildizi (SCHEMA.md §2). Ishlatilmaydigan
 * variant `is_active = false` qilinadi.
 *
 * Ko'rish tovarning o'zi bilan birga boradi — alohida `variant.view`
 * ruxsati yo'q.
 */
final class ProductVariantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('catalog.product.view_any');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('catalog.product.view');
    }

    public function create(User $user): bool
    {
        return $user->can('catalog.variant.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('catalog.variant.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
