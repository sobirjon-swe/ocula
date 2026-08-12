<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Controllers;

use App\Modules\Warehouse\Http\Requests\Stock\ReverseMovementRequest;
use App\Modules\Warehouse\Http\Resources\StockBalanceResource;
use App\Modules\Warehouse\Http\Resources\StockMovementResource;
use App\Modules\Warehouse\Models\StockBalance;
use App\Modules\Warehouse\Models\StockMovement;
use App\Modules\Warehouse\Services\StockLedger;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Qoldiq va harakatlar daftari — PROJECT.md 7.1, 7.21.
 *
 * Yozadigan yagona amal — **storno**. Ombor boshqa hech qanday
 * to'g'ridan-to'g'ri endpoint orqali o'zgarmaydi: har o'zgarish hujjatdan
 * kelib chiqadi (kirim, sotuv, transfer).
 */
final class StockController extends ApiController
{
    /**
     * Qoldiqlar — kesh jadvalidan (7.1).
     */
    public function balances(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StockBalance::class);

        $balances = QueryBuilder::for(StockBalance::class)
            ->allowedFilters(
                AllowedFilter::exact('variant_id'),
                AllowedFilter::exact('location_id'),
                AllowedFilter::exact('branch_id'),
            )
            ->allowedSorts('quantity')
            ->defaultSort('variant_id')
            ->available()
            ->with('variant')
            ->paginate($this->perPage($request, 50))
            ->withQueryString();

        return StockBalanceResource::collection($balances);
    }

    /**
     * Bitta variantning qoldig'i — **daftardan** hisoblanadi, keshdan
     * emas: kassa ekrani "sotsam bo'ladimi" degan savolga aniq javob
     * olishi kerak.
     */
    public function balanceOf(Request $request, int $variantId, int $locationId, StockLedger $ledger): JsonResponse
    {
        $this->authorize('viewAny', StockBalance::class);

        return ApiResponse::data([
            'variant_id' => $variantId,
            'location_id' => $locationId,
            'quantity' => $ledger->balanceOf($variantId, $locationId),
        ]);
    }

    public function movements(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StockMovement::class);

        $movements = QueryBuilder::for(StockMovement::class)
            ->allowedFilters(
                AllowedFilter::exact('variant_id'),
                AllowedFilter::exact('location_id'),
                AllowedFilter::exact('branch_id'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('cost_incomplete'),
            )
            ->allowedSorts('created_at', 'quantity')
            ->defaultSort('-created_at', '-id')
            ->with('variant')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return StockMovementResource::collection($movements);
    }

    /**
     * Storno — PROJECT.md 7.21. Asl yozuv o'z joyida qoladi.
     */
    public function reverse(
        ReverseMovementRequest $request,
        StockMovement $movement,
        StockLedger $ledger,
    ): JsonResponse {
        $this->authorize('reverse', $movement);

        $reversal = $ledger->reverse(
            $this->currentUser($request),
            $movement,
            $request->string('reason')->toString(),
        );

        return ApiResponse::created((new StockMovementResource($reversal))->resolve($request));
    }
}
