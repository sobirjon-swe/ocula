<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Debt;

use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\DebtStatus;
use App\Modules\Finance\Models\Debt;
use App\Modules\Finance\Services\DebtRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Qarzni hisobdan chiqarish — PERMISSIONS.md §8 (faqat direktor).
 *
 * Buyurtmaning o'zi tegilmaydi (`orders.debt` shu qarz uchun qolaveradi) —
 * bu faqat moliyaviy hisobot uchun "undirilmaydi" degan belgi, savdo
 * tarixini o'zgartirmaydi.
 */
final class WriteOffDebt
{
    public function handle(User $author, Debt $debt, string $reason): Debt
    {
        return DB::transaction(function () use ($author, $debt, $reason): Debt {
            $debt = Debt::withoutGlobalScopes()->lockForUpdate()->findOrFail($debt->id);
            if ($debt->status->isFinal()) {
                throw ValidationException::withMessages([
                    'debt' => __('finance::debt.already_closed'),
                ]);
            }

            $debt->update([
                'status' => DebtStatus::WrittenOff->value,
                'write_off_reason' => $reason,
                'closed_at' => CarbonImmutable::now(),
                'approved_by' => $author->id,
            ]);
            app(DebtRegistry::class)->syncCustomerBalance($debt->customer_id);

            return $debt;
        });
    }
}
