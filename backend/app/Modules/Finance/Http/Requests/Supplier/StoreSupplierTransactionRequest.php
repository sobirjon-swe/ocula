<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Supplier;

use App\Modules\Finance\Enums\SupplierTxType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Qo'lda to'lov/tuzatish — SCHEMA.md `supplier_transactions`.
 *
 * `purchase` turi bu yerdan yaratilmaydi (`SupplierTxType::manual()`) —
 * u faqat `ReceivePurchase` dan avtomatik tug'iladi.
 *
 * `payment` uchun `branch_id` majburiy (kassadan chiqim shu filialga
 * yoziladi), `correction` uchun `reason` majburiy va summa manfiy ham
 * bo'lishi mumkin.
 */
class StoreSupplierTransactionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $isPayment = $this->string('type')->toString() === SupplierTxType::Payment->value;

        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'type' => [
                'required',
                Rule::enum(SupplierTxType::class)->only(SupplierTxType::manual()),
            ],
            'branch_id' => [$isPayment ? 'required' : 'nullable', 'integer', 'exists:branches,id'],
            'amount' => $isPayment
                ? ['required', 'numeric', 'gt:0', 'max:9999999999999']
                : ['required', 'numeric', 'not_in:0', 'between:-9999999999999,9999999999999'],
            'purchase_id' => ['nullable', 'integer', 'exists:purchases,id'],
            'reason' => [$isPayment ? 'nullable' : 'required', 'string', 'max:255'],
        ];
    }
}
