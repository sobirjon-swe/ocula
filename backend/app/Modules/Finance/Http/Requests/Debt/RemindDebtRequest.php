<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Requests\Debt;

use App\Modules\Finance\Enums\ReminderChannel;
use App\Modules\Finance\Enums\ReminderResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Qo'lda qarz eslatmasi (CRM) — SCHEMA.md `debt_reminders`.
 *
 * `telegram` kanal bu yerdan tanlanmaydi (`ReminderChannel::manual()`) —
 * u faqat `telegram:remind-debts` buyrug'idan avtomatik yoziladi.
 */
class RemindDebtRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'channel' => [
                'required',
                Rule::enum(ReminderChannel::class)->only(ReminderChannel::manual()),
            ],
            'response' => ['nullable', Rule::enum(ReminderResponse::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
