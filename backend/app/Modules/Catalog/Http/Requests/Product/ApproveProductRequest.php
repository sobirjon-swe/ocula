<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Tasdiqlash yoki rad etish — PROJECT.md 7.17.
 *
 * Rad etilgan tovar sotilmaydi, lekin qoldig'i yo'qolmaydi: u asosiy
 * tovarga birlashtiriladi. Shuning uchun rad etishda `merge_into`
 * ko'rsatish tavsiya etiladi — u yerda alohida endpoint bor.
 */
class ApproveProductRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'approved' => ['required', 'boolean'],
        ];
    }
}
