<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\Price\SetPrice;
use App\Modules\Catalog\Http\Requests\Price\StorePriceRequest;
use App\Modules\Catalog\Http\Resources\PriceResource;
use App\Modules\Catalog\Models\Price;
use App\Modules\Catalog\Models\ProductVariant;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Variant narxlari — SCHEMA.md §2, ANALIZ 3.16.
 *
 * Narx yozuvi tahrirlanmaydi: yangi narx qo'yilganda avvalgisining
 * `valid_to` yopiladi. Shuning uchun bu yerda faqat `index`, `store`
 * va "hozirgi narx" bor.
 */
final class PriceController extends ApiController
{
    public function index(Request $request, ProductVariant $variant): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Price::class);

        $prices = $variant->prices()
            ->orderByDesc('valid_from')
            ->paginate($this->perPage($request));

        return PriceResource::collection($prices);
    }

    /**
     * Amaldagi narx: filial narxi bo'lsa u, bo'lmasa global (ANALIZ 3.16).
     */
    public function current(Request $request, ProductVariant $variant): JsonResponse
    {
        $this->authorize('viewAny', Price::class);

        $branchId = $request->has('branch_id') ? $request->integer('branch_id') : null;
        $price = Price::resolveFor($variant->id, $branchId);

        return ApiResponse::data(
            $price instanceof Price ? (new PriceResource($price))->resolve($request) : null,
        );
    }

    public function store(StorePriceRequest $request, ProductVariant $variant, SetPrice $setPrice): JsonResponse
    {
        $this->authorize('create', Price::class);

        $price = $setPrice->handle(
            $this->currentUser($request),
            $variant,
            Money::of($request->string('price')->toString()),
            $request->has('branch_id') ? $request->integer('branch_id') : null,
            $request->date('valid_from'),
        );

        return ApiResponse::created((new PriceResource($price))->resolve($request));
    }
}
