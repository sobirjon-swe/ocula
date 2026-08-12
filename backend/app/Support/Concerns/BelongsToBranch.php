<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use App\Modules\Core\Models\Branch;
use App\Support\Contracts\HasBranchAccess;
use App\Support\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * `branch_id` ustuni bo'lgan model uchun — PROJECT.md §4.
 *
 * - `BranchScope` global scope sifatida ulanadi (avtomatik filtr);
 * - yangi yozuvda `branch_id` bo'sh bo'lsa, foydalanuvchining filiali
 *   qo'yiladi (faqat bitta filialga biriktirilgan bo'lsa — aks holda
 *   ko'p filialli xodim `branch_id` ni ataylab ko'rsatishi kerak).
 *
 * `stock_movements` da `branch_id` denormalizatsiya qilingan (ANALIZ 3.10) —
 * shuning uchun u ham shu trait'dan foydalanadi.
 *
 * @phpstan-require-extends Model
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('branch_id') !== null) {
                return;
            }

            $user = Auth::user();

            if (! $user instanceof HasBranchAccess) {
                return;
            }

            $branchIds = $user->accessibleBranchIds();

            if (count($branchIds) === 1) {
                $model->setAttribute('branch_id', $branchIds[0]);
            }
        });
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
