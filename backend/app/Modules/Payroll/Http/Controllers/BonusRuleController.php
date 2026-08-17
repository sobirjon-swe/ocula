<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Http\Controllers;

use App\Modules\Payroll\Http\Requests\StoreBonusRuleRequest;
use App\Modules\Payroll\Http\Resources\BonusRuleResource;
use App\Modules\Payroll\Models\BonusRule;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Mukofot qoidasi — PROJECT.md 7.19, BOSQICH-10.md §10b.
 */
final class BonusRuleController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', BonusRule::class);

        $rules = QueryBuilder::for(BonusRule::class)
            ->allowedFilters(
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('role'),
                AllowedFilter::exact('branch_id'),
            )
            ->allowedSorts('valid_from', 'created_at')
            ->defaultSort('-valid_from')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return BonusRuleResource::collection($rules);
    }

    public function store(StoreBonusRuleRequest $request): JsonResponse
    {
        $this->authorize('create', BonusRule::class);

        $rule = BonusRule::create([
            ...$request->validated(),
            'created_by' => $this->currentUser($request)->id,
        ]);

        return ApiResponse::created((new BonusRuleResource($rule))->resolve($request));
    }
}
