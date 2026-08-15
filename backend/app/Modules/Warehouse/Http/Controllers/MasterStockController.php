<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Controllers;

use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Http\Requests\MasterStock\IssueToMasterRequest;
use App\Modules\Warehouse\Http\Resources\StockBalanceResource;
use App\Modules\Warehouse\Models\StockBalance;
use App\Modules\Warehouse\Services\MasterStock;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Usta zaxirasi — PROJECT.md §6.6, 7.1, ANALIZ 3.2.
 *
 * "Usta zaxirasi" alohida jadval emas: u `master` turidagi `location`,
 * egasi ustaning o'zi. Shuning uchun bu yerdagi ro'yxat oddiy qoldiq
 * ro'yxati — faqat joyi boshqa.
 */
final class MasterStockController extends ApiController
{
    /**
     * Ustaning qo'lidagi qoldiq. `master_id` berilmasa — so'rov
     * yuborgan odamning o'zi (usta o'z zaxirasini ko'radi).
     */
    public function index(Request $request, MasterStock $masterStock): AnonymousResourceCollection
    {
        $this->authorize('viewMasterStock', StockBalance::class);

        $master = $this->masterFrom($request);
        $branchId = $request->integer('branch_id') ?: $master->branch_id;

        if ($branchId === null) {
            throw ValidationException::withMessages([
                'branch_id' => __('warehouse::master_stock.branch_required'),
            ]);
        }

        $location = $masterStock->locationFor($master, (int) $branchId);

        // Joy bo'yicha cheklov so'rovga oldindan qo'shiladi: usta
        // zaxirasi — bitta aniq location, uni filtr sifatida ochib
        // qo'yishning ma'nosi yo'q.
        $inReserve = StockBalance::query()
            ->where('location_id', $location->id)
            ->where('quantity', '>', 0);

        $balances = QueryBuilder::for($inReserve)
            ->allowedSorts('quantity')
            ->defaultSort('variant_id')
            ->with('variant')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return StockBalanceResource::collection($balances);
    }

    /**
     * Ombordan usta zaxirasiga berish — tovar filialdan chiqmaydi,
     * faqat joyi o'zgaradi.
     */
    public function store(IssueToMasterRequest $request, MasterStock $masterStock): JsonResponse
    {
        $this->authorize('issueToMaster', StockBalance::class);

        // Ombor `BranchScope` bilan qidiriladi: begona filial omborini
        // ko'rsatgan so'rov 404 oladi.
        $from = Location::findOrFail($request->integer('location_id'));

        $location = $masterStock->issueTo(
            $this->currentUser($request),
            User::findOrFail($request->integer('master_id')),
            $from,
            $request->integer('variant_id'),
            $request->integer('quantity'),
        );

        return ApiResponse::created([
            'location_id' => $location->id,
            'master_id' => $location->owner_id,
            'branch_id' => $location->branch_id,
        ]);
    }

    private function masterFrom(Request $request): User
    {
        $masterId = $request->integer('master_id');

        return $masterId === 0 ? $this->currentUser($request) : User::findOrFail($masterId);
    }
}
