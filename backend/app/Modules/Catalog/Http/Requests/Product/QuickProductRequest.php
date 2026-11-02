<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests\Product;

final class QuickProductRequest extends StoreProductRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'barcode' => ['nullable', 'string', 'max:64', 'unique:product_variants,barcode'],
            'sku' => ['nullable', 'string', 'max:64', 'unique:product_variants,sku'],
            'branch_id' => ['required_with:opening_quantity', 'nullable', 'integer', 'exists:branches,id'],
            'opening_quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'cost_price' => ['required_with:opening_quantity', 'nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
        ];
    }
}
