<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Debt;

use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\ReminderChannel;
use App\Modules\Finance\Enums\ReminderResponse;
use App\Modules\Finance\Models\Debt;
use App\Modules\Finance\Models\DebtReminder;
use Carbon\CarbonImmutable;

/**
 * Qo'lda qarz eslatmasi (CRM) — BOSQICH-7.md §5 #2, BOSQICH-10.md §10a.
 *
 * `telegram_notifications` bilan bir qatorda yashaydi: u "yuborildimi"
 * degan texnik jurnal, bu esa xodimning qo'ng'iroq/uchrashuv natijasi.
 *
 * Javob `paid` bo'lsa ham qarzning o'zi shu yerdan yopilmaydi — pul
 * haqiqatan tushganda `DebtRegistry` uni `payments` orqali yopadi,
 * aks holda "aytdi" bilan "to'ladi" chalkashib ketardi.
 */
final class RecordDebtReminder
{
    public function handle(
        ?User $author,
        Debt $debt,
        ReminderChannel $channel,
        ReminderResponse $response = ReminderResponse::None,
        ?string $note = null,
    ): DebtReminder {
        return DebtReminder::create([
            'debt_id' => $debt->id,
            'sent_at' => CarbonImmutable::now(),
            'channel' => $channel->value,
            'response' => $response->value,
            'responded_at' => $response === ReminderResponse::None ? null : CarbonImmutable::now(),
            'note' => $note,
            'created_by' => $author?->id,
        ]);
    }
}
