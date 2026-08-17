<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Controllers;

use App\Modules\Warehouse\Http\Requests\LostSale\StoreLostSaleRequest;
use App\Modules\Warehouse\Http\Resources\LostSaleResource;
use App\Modules\Warehouse\Models\LostSale;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Yo'qotilgan savdo — PROJECT.md 7.9, BOSQICH-10.md §10c.
 *
 * "Usta faqat sababni tanlaydi" naqshiga o'xshab, sotuvchi faqat
 * sabab va tovarni (yoki qidiruv matnini) yozadi — tahrirlash yo'q,
 * bu **analitik log**, o'zgarmas voqea.
 */
final class LostSaleController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', LostSale::class);

        $lostSales = QueryBuilder::for(LostSale::class)
            ->allowedFilters(
                AllowedFilter::exact('branch_id'),
                AllowedFilter::exact('variant_id'),
                AllowedFilter::exact('reason'),
            )
            ->allowedSorts('created_at')
            ->defaultSort('-created_at', '-id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return LostSaleResource::collection($lostSales);
    }

    public function store(StoreLostSaleRequest $request): JsonResponse
    {
        $this->authorize('create', LostSale::class);

        $lostSale = LostSale::create([
            ...$request->validated(),
            'created_by' => $this->currentUser($request)->id,
        ]);

        return ApiResponse::created((new LostSaleResource($lostSale))->resolve($request));
    }
}
