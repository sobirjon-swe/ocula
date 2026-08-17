<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Finance\Enums\SupplierTxType;
use App\Modules\Finance\Http\Requests\Supplier\StoreSupplierTransactionRequest;
use App\Modules\Finance\Http\Resources\SupplierTransactionResource;
use App\Modules\Finance\Models\SupplierTransaction;
use App\Modules\Finance\Services\SupplierLedger;
use App\Modules\Warehouse\Models\Purchase;
use App\Modules\Warehouse\Models\Supplier;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Yetkazib beruvchi bilan ikki tomonlama hisob — PROJECT.md 7.15,
 * BOSQICH-10.md §10a.
 *
 * Filialga bog'lanmagan — ro'yxat `BranchScope` bilan cheklanmaydi,
 * yetkazib beruvchi butun tarmoq bilan ishlaydi.
 */
final class SupplierTransactionController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SupplierTransaction::class);

        $transactions = QueryBuilder::for(SupplierTransaction::class)
            ->allowedFilters(
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::exact('type'),
            )
            ->allowedSorts('created_at', 'amount')
            ->defaultSort('-created_at', '-id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return SupplierTransactionResource::collection($transactions);
    }

    public function store(StoreSupplierTransactionRequest $request, SupplierLedger $ledger): JsonResponse
    {
        $this->authorize('create', SupplierTransaction::class);

        $actor = $this->currentUser($request);
        $supplier = Supplier::query()->findOrFail($request->integer('supplier_id'));
        $type = SupplierTxType::from($request->string('type')->toString());
        $amount = Money::of($request->string('amount')->toString());
        $purchase = $request->filled('purchase_id')
            ? Purchase::query()->find($request->integer('purchase_id'))
            : null;

        $transaction = $type === SupplierTxType::Payment
            ? $ledger->recordPayment(
                $actor,
                $supplier,
                $request->integer('branch_id'),
                $amount,
                $purchase,
                $request->string('reason')->toString() ?: null,
            )
            : $ledger->recordCorrection($actor, $supplier, $amount, $request->string('reason')->toString());

        return ApiResponse::created((new SupplierTransactionResource($transaction))->resolve($request));
    }

    public function balance(Supplier $supplier, SupplierLedger $ledger): JsonResponse
    {
        $this->authorize('viewBalance', Supplier::class);

        $balance = $ledger->balance($supplier);

        return ApiResponse::data([
            'supplier_id' => $supplier->id,
            'balance' => $balance->toString(),
            'balance_formatted' => $balance->format(),
        ]);
    }
}
