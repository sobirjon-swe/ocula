<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Actions\Defect;

use App\Modules\Core\Models\User;
use App\Modules\Finance\Services\SupplierLedger;
use App\Modules\Warehouse\Models\Defect;
use App\Modules\Warehouse\Models\Purchase;
use App\Modules\Warehouse\Models\SupplierReturn;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PostSupplierReturn
{
    public function handle(User $actor, SupplierReturn $act, Purchase $purchase, SupplierLedger $ledger): SupplierReturn
    {
        return DB::transaction(function () use ($actor, $act, $purchase, $ledger): SupplierReturn {
            $act = SupplierReturn::query()->lockForUpdate()->findOrFail($act->id);
            if ($act->status !== 'draft' || ($act->purchase_id !== null && $act->purchase_id !== $purchase->id)) {
                throw ValidationException::withMessages(['purchase_id' => __('warehouse::defect.return_already_posted')]);
            }
            $defect = Defect::findOrFail($act->defect_id);
            if ($act->purchase_id === null && ! $purchase->items()->where('variant_id', $defect->variant_id)->exists()) {
                throw ValidationException::withMessages(['purchase_id' => __('warehouse::defect.return_wrong_purchase')]);
            }
            $act->update(['purchase_id' => $purchase->id, 'supplier_id' => $purchase->supplier_id, 'status' => 'posted', 'posted_by' => $actor->id, 'posted_at' => now()]);
            $ledger->recordReturn($actor, $purchase, Money::of($act->amount), $act->id);

            return $act;
        });
    }
}
