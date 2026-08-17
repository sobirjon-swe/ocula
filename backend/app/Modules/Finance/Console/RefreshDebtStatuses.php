<?php

declare(strict_types=1);

namespace App\Modules\Finance\Console;

use App\Modules\Finance\Services\DebtRegistry;
use Illuminate\Console\Command;

/**
 * Qarz holatini vaqt bo'yicha yangilaydi — PROJECT.md 7.6, BOSQICH-10.md §10a.
 *
 * Holat (`open`→`overdue`→`doubtful`) to'lov hodisasisiz ham o'zgarishi
 * kerak: bugun ochiq bo'lgan qarz ertaga muddati o'tgan bo'lib qolishi
 * mumkin. Shuning uchun kunlik alohida buyruq.
 */
final class RefreshDebtStatuses extends Command
{
    protected $signature = 'finance:refresh-debt-statuses';

    protected $description = 'Qarzlar holatini muddatga qarab yangilaydi (open/overdue/doubtful)';

    public function handle(DebtRegistry $registry): int
    {
        $changed = $registry->refreshStatuses();

        $this->info("Yangilandi: {$changed}");

        return self::SUCCESS;
    }
}
