<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Finance\Http\Requests\ExpenseCategory\StoreExpenseCategoryRequest;
use App\Modules\Finance\Http\Resources\ExpenseCategoryResource;
use App\Modules\Finance\Models\ExpenseCategory;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Xarajat kategoriyalari — PROJECT.md §6.8.
 */
final class ExpenseCategoryController extends ApiController
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ExpenseCategory::class);

        return ExpenseCategoryResource::collection(
            ExpenseCategory::query()->orderBy('name')->get(),
        );
    }

    public function store(StoreExpenseCategoryRequest $request): JsonResponse
    {
        $this->authorize('create', ExpenseCategory::class);

        $category = ExpenseCategory::create($request->validated());

        return ApiResponse::created((new ExpenseCategoryResource($category))->resolve($request));
    }
}
