<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Controllers;

use App\Modules\Finance\Services\SupplierLedger;
use App\Modules\Warehouse\Actions\Defect\PostSupplierReturn;
use App\Modules\Warehouse\Http\Requests\PostSupplierReturnRequest;
use App\Modules\Warehouse\Models\Purchase;
use App\Modules\Warehouse\Models\SupplierReturn;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SupplierReturnController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($this->currentUser($request)->can('warehouse.defect.view_any'), 403);

        return response()->json(SupplierReturn::query()->latest('id')->paginate($this->perPage($request)));
    }

    public function post(PostSupplierReturnRequest $request, SupplierReturn $supplierReturn, PostSupplierReturn $action, SupplierLedger $ledger): JsonResponse
    {
        abort_unless($this->currentUser($request)->can('warehouse.defect.approve'), 403);
        $purchase = Purchase::findOrFail($request->integer('purchase_id'));

        return ApiResponse::data($action->handle($this->currentUser($request), $supplierReturn, $purchase, $ledger)->toArray());
    }
}
