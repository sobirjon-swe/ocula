<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Http\Controllers;

use App\Modules\Payroll\Http\Requests\StoreBranchPlanRequest;
use App\Modules\Payroll\Http\Resources\BranchPlanResource;
use App\Modules\Payroll\Models\BranchPlan;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Filial oylik rejasi — PROJECT.md 7.18, BOSQICH-10.md §10b.
 */
final class BranchPlanController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', BranchPlan::class);

        $plans = QueryBuilder::for(BranchPlan::class)
            ->allowedFilters(
                AllowedFilter::exact('branch_id'),
                AllowedFilter::exact('period'),
                AllowedFilter::exact('type'),
            )
            ->allowedSorts('period', 'created_at')
            ->defaultSort('-period')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return BranchPlanResource::collection($plans);
    }

    public function store(StoreBranchPlanRequest $request): JsonResponse
    {
        $this->authorize('create', BranchPlan::class);

        $plan = BranchPlan::create([
            ...$request->validated(),
            'created_by' => $this->currentUser($request)->id,
        ]);

        return ApiResponse::created((new BranchPlanResource($plan))->resolve($request));
    }
}
