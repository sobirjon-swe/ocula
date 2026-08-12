<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions\Product;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Dublikatni asosiy tovarga birlashtirish — PROJECT.md 7.13, 7.17.
 *
 * Dublikat **o'chirilmaydi**: `merged_into_id` orqali asosiyga ishora
 * qiladi va `status = rejected` bo'ladi. Uning ombor harakati va sotuv
 * tarixi joyida qoladi, analitika esa ularni asosiy tovar ostida
 * ko'rsatadi.
 *
 * Variantlar ko'chirilmaydi — variantda `product_id` bor va uni
 * o'zgartirish ombor qatlamlarining ildizini uzib qo'yardi.
 */
final class MergeProducts
{
    public function handle(User $author, Product $duplicate, Product $target): Product
    {
        if ($duplicate->id === $target->id) {
            throw ValidationException::withMessages([
                'merge_into_id' => __('catalog::product.merge_into_itself'),
            ]);
        }

        if ($target->merged_into_id !== null) {
            throw ValidationException::withMessages([
                'merge_into_id' => __('catalog::product.merge_into_merged'),
            ]);
        }

        return DB::transaction(function () use ($author, $duplicate, $target): Product {
            $duplicate->update([
                'status' => ProductStatus::Rejected,
                'is_active' => false,
            ]);

            $duplicate->forceFill([
                'merged_into_id' => $target->id,
                'approved_by' => $author->id,
                'approved_at' => now(),
            ])->save();

            return $duplicate;
        });
    }
}
