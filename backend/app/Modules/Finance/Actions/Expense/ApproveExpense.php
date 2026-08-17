<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Expense;

use App\Modules\Core\Models\User;
use App\Modules\Finance\Models\Expense;
use Illuminate\Validation\ValidationException;

/**
 * Xarajatni tasdiqlash — PROJECT.md §6.8.
 *
 * Kassadan chiqim xarajat yozilganda allaqachon o'tgan — tasdiqlash
 * pulni qaytadan harakatlantirmaydi, faqat "kim tekshirdi" degan
 * savolga javob qo'yadi (masalan katta summali xarajat uchun).
 */
final class ApproveExpense
{
    public function handle(User $approver, Expense $expense): Expense
    {
        if ($expense->approved_by !== null) {
            throw ValidationException::withMessages([
                'expense' => __('finance::expense.already_approved'),
            ]);
        }

        $expense->update(['approved_by' => $approver->id]);

        return $expense;
    }
}
