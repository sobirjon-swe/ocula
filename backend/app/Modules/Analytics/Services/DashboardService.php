<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Services;

use App\Modules\Core\Models\User;
use Carbon\CarbonImmutable;

/**
 * Bosh ekran — PROJECT.md 7.8, 7.18, BOSQICH-10.md §10c.
 *
 * Majburiy qator: "Bajarilmagan buyurtmalar majburiyati" (7.8) — bu
 * qanchalik oddiy tuyulmasin, direktor har kuni ko'radigan yagona
 * raqam, shuning uchun bosh ekranda alohida ajratilgan.
 */
final class DashboardService
{
    public function __construct(
        private readonly CashReportService $cashReport,
        private readonly ProfitReportService $profitReport,
        private readonly BranchRankingService $branchRanking,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(User $user, ?int $branchId = null): array
    {
        $today = CarbonImmutable::today();
        $cashToday = $this->cashReport->generate($user, $today, $today, $branchId);
        $outstanding = $this->profitReport->outstandingLiability($user, $branchId);
        $ranking = $this->branchRanking->generate($user, $today->format('Y-m'));

        return [
            'date' => $today->toDateString(),
            'cash_net_today' => $cashToday['net_total'],
            'outstanding_liability' => $outstanding->toString(),
            'branch_ranking' => $ranking,
        ];
    }
}
