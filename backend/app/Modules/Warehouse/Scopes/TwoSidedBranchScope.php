<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Scopes;

use App\Support\Contracts\HasBranchAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;

/**
 * Ikki tomonlama hujjatlar uchun filial cheklovi — PROJECT.md §4.
 *
 * Transfer va ichki so'rovda bitta `branch_id` yo'q: ularning **ikkita
 * tomoni** bor. Oddiy `BranchScope` bunga yaramaydi — u bitta ustunga
 * qaraydi va qabul qiluvchi filial o'ziga kelayotgan tovarni ko'rmay
 * qolardi.
 *
 * Shu sababli cheklov "shu ikki ustundan **birortasi** mening
 * filialimga tegishlimi" degan savolga aylanadi.
 *
 * Qiyoslanadigan ustun to'g'ridan-to'g'ri filial bo'lishi ham
 * (`stock_requests.from_branch_id`), location orqali bo'lishi ham
 * mumkin (`transfers.from_location_id`) — shuning uchun `$through`
 * bayrog'i bor.
 *
 * @implements Scope<Model>
 */
final class TwoSidedBranchScope implements Scope
{
    /**
     * @param  string  $firstColumn  masalan `from_location_id`
     * @param  string  $secondColumn  masalan `to_location_id`
     * @param  bool  $throughLocations  ustunlar `locations` ga ishora qiladimi
     */
    public function __construct(
        private readonly string $firstColumn,
        private readonly string $secondColumn,
        private readonly bool $throughLocations = false,
    ) {}

    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user instanceof HasBranchAccess || $user->canAccessAllBranches()) {
            return;
        }

        $branchIds = $user->accessibleBranchIds();

        if ($branchIds === []) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $first = $model->qualifyColumn($this->firstColumn);
        $second = $model->qualifyColumn($this->secondColumn);

        if (! $this->throughLocations) {
            $builder->where(function (Builder $query) use ($first, $second, $branchIds): void {
                $query->whereIn($first, $branchIds)->orWhereIn($second, $branchIds);
            });

            return;
        }

        // Ustun `locations` ga ishora qiladi — filialni bir qadam
        // orqali topamiz. Join o'rniga ichki so'rov: `orWhereIn` bilan
        // join qatorlarni ikkilantirib yuborardi.
        $locations = static function (QueryBuilder $query) use ($branchIds): void {
            $query->select('id')->from('locations')->whereIn('branch_id', $branchIds);
        };

        $builder->where(function (Builder $query) use ($first, $second, $locations): void {
            $query->whereIn($first, $locations)->orWhereIn($second, $locations);
        });
    }
}
