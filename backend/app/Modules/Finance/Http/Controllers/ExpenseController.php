<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Finance\Actions\Expense\ApproveExpense;
use App\Modules\Finance\Actions\Expense\CreateExpense;
use App\Modules\Finance\Http\Requests\Expense\StoreExpenseRequest;
use App\Modules\Finance\Http\Resources\ExpenseResource;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\ExpenseCategory;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Xarajatlar — PROJECT.md §6.8, BOSQICH-10.md §10a.
 *
 * Insert-only (7.21): `update`/`destroy` yo'q, faqat `approve`.
 */
final class ExpenseController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Expense::class);

        $expenses = QueryBuilder::for(Expense::class)
            ->allowedFilters(
                AllowedFilter::exact('branch_id'),
                AllowedFilter::exact('category_id'),
            )
            ->with('category')
            ->allowedSorts('date', 'amount', 'created_at')
            ->defaultSort('-date', '-id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return ExpenseResource::collection($expenses);
    }

    public function store(StoreExpenseRequest $request, CreateExpense $action): JsonResponse
    {
        $this->authorize('create', Expense::class);

        $actor = $this->currentUser($request);
        $branchId = $request->integer('branch_id');

        if (! $actor->canAccessAllBranches() && ! in_array($branchId, $actor->accessibleBranchIds(), true)) {
            throw ValidationException::withMessages([
                'branch_id' => __('core::shift.foreign_branch'),
            ]);
        }

        $category = ExpenseCategory::query()->findOrFail($request->integer('category_id'));

        $expense = $action->handle(
            $actor,
            $branchId,
            $category,
            Money::of($request->string('amount')->toString()),
            $request->filled('date') ? CarbonImmutable::parse($request->string('date')->toString()) : null,
            $request->string('description')->toString() ?: null,
            receiptPath: $request->string('receipt_path')->toString() ?: null,
        );

        return ApiResponse::created((new ExpenseResource($expense->load('category')))->resolve($request));
    }

    public function show(Request $request, Expense $expense): ExpenseResource
    {
        $this->authorize('view', $expense);

        return new ExpenseResource($expense->load('category'));
    }

    public function approve(Request $request, Expense $expense, ApproveExpense $action): JsonResponse
    {
        $this->authorize('approve', $expense);

        $expense = $action->handle($this->currentUser($request), $expense);

        return ApiResponse::data((new ExpenseResource($expense->load('category')))->resolve($request));
    }
}
