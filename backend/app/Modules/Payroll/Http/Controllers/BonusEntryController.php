<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Http\Controllers;

use App\Modules\Payroll\Actions\ApproveBonusEntry;
use App\Modules\Payroll\Actions\PayBonusEntry;
use App\Modules\Payroll\Http\Requests\PayBonusEntryRequest;
use App\Modules\Payroll\Http\Resources\BonusEntryResource;
use App\Modules\Payroll\Models\BonusEntry;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Mukofot yozuvi — PROJECT.md 7.12, BOSQICH-10.md §10b.
 *
 * Insert-only ro'yxat: `store`/`update`/`destroy` yo'q — yozuv faqat
 * `BonusAccrual` orqali tug'iladi.
 */
final class BonusEntryController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', BonusEntry::class);

        $actor = $this->currentUser($request);

        $entries = QueryBuilder::for(BonusEntry::class)
            ->when(
                ! $actor->can('payroll.bonus.view_any'),
                fn ($query) => $query->where('user_id', $actor->id),
            )
            ->allowedFilters(
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('period'),
                AllowedFilter::exact('status'),
            )
            ->allowedSorts('period', 'amount', 'created_at')
            ->defaultSort('-created_at', '-id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return BonusEntryResource::collection($entries);
    }

    public function approve(Request $request, BonusEntry $entry, ApproveBonusEntry $action): JsonResponse
    {
        $this->authorize('approve', BonusEntry::class);

        $entry = $action->handle($this->currentUser($request), $entry);

        return ApiResponse::data((new BonusEntryResource($entry))->resolve($request));
    }

    public function pay(PayBonusEntryRequest $request, BonusEntry $entry, PayBonusEntry $action): JsonResponse
    {
        $this->authorize('pay', BonusEntry::class);

        $entry = $action->handle($this->currentUser($request), $entry, $request->integer('branch_id'));

        return ApiResponse::data((new BonusEntryResource($entry))->resolve($request));
    }
}
