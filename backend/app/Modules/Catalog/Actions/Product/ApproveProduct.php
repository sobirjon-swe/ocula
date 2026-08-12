<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions\Product;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Models\User;

/**
 * Tovarni tasdiqlash yoki rad etish — PROJECT.md 7.17.
 *
 * **Tasdiqlash savdoni to'smaydi**: `pending` tovar ham sotiladi va
 * qoldiqda bo'ladi. Tasdiq faqat analitikaga ta'sir qiladi —
 * tekshirilmagan tovarlar alohida qatorda ko'rsatiladi.
 *
 * Rad etilgan tovar sotilmaydi (`Product::scopeSellable`), lekin
 * o'chirilmaydi: qoldig'i asosiy tovarga birlashtiriladi
 * (`MergeProducts`).
 */
final class ApproveProduct
{
    public function handle(User $approver, Product $product, bool $approved): Product
    {
        $product->update([
            'status' => $approved ? ProductStatus::Approved : ProductStatus::Rejected,
        ]);

        $product->forceFill([
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ])->save();

        return $product;
    }
}
