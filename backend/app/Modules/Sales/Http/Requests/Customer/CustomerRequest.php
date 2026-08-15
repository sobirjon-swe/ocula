<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Requests\Customer;

use App\Modules\Sales\Models\Customer;
use App\Support\Enums\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mijoz kartochkasi — SCHEMA.md §4.
 *
 * Telefon — yagona ishonchli identifikator: mijoz o'z ismini har xil
 * yozdirishi mumkin, lekin raqami bitta bo'ladi. Shu sababli u unique
 * va yumshoq o'chirilganlar orasida ham tekshiriladi (`withTrashed`
 * emas: o'chirilgan mijoz qaytib kelsa, uni tiklash kerak, dublikat
 * ochish emas).
 */
class CustomerRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $customer = $this->route('customer');
        $id = $customer instanceof Customer ? $customer->id : null;

        return [
            'name' => ['required', 'string', 'max:160'],
            'phone' => [
                'required', 'string', 'max:32',
                Rule::unique('customers', 'phone')->ignore($id),
            ],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'telegram_id' => [
                'nullable', 'integer',
                Rule::unique('customers', 'telegram_id')->ignore($id),
            ],
            'locale' => ['nullable', Rule::enum(Locale::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ];
    }
}
