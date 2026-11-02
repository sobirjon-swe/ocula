<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Http\Controllers;

use App\Modules\Analytics\Services;
use App\Support\Http\ApiController;
use App\Support\Http\CsvResponse;
use App\Support\Http\XlsxResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ReportExportController extends ApiController
{
    public function __invoke(Request $request, string $report): Response
    {
        $user = $this->currentUser($request);
        $permission = match ($report) {
            'cash-report' => 'analytics.cash_report.view', 'profit-report' => 'analytics.profit_report.view',
            'abc-analysis', 'dead-stock' => 'analytics.stock_report.view', 'lost-sale-report' => 'warehouse.lost_sale.view_any',
            'staff-report' => 'analytics.staff_report.view', 'branch-rating' => 'analytics.branch_rating.view', default => '',
        };
        abort_unless($permission !== '' && $user->can($permission) && $user->can('analytics.export'), 403);
        $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'], 'period' => ['nullable', 'date_format:Y-m'], 'days' => ['nullable', 'integer', 'between:1,3650']]);
        $to = CarbonImmutable::parse($request->input('to') ?: CarbonImmutable::today('Asia/Tashkent')->toDateString(), 'Asia/Tashkent');
        $from = CarbonImmutable::parse($request->input('from') ?: $to->startOfMonth()->toDateString(), 'Asia/Tashkent');
        $branch = $request->integer('branch_id') ?: null;
        $period = $request->string('period')->toString() ?: $to->format('Y-m');
        $result = match ($report) {
            'cash-report' => app(Services\CashReportService::class)->generate($user, $from, $to, $branch),
            'profit-report' => app(Services\ProfitReportService::class)->generate($user, $from, $to, $branch),
            'abc-analysis' => app(Services\AbcAnalysisService::class)->generate($user, $from, $to, $branch),
            'dead-stock' => app(Services\DeadStockReportService::class)->generate($user, $branch, $request->integer('days') ?: 90),
            'lost-sale-report' => app(Services\LostSaleReportService::class)->generate($user, $from, $to, $branch),
            'staff-report' => app(Services\StaffPerformanceReportService::class)->generate($user, $period, $branch),
            'branch-rating' => app(Services\BranchRankingService::class)->generate($user, $period),
        };
        $rows = array_is_list($result) ? $result : [$result];
        if ($request->input('format') === 'csv') {
            $headers = $rows === [] ? [] : array_map(static fn (int|string $key): string => (string) $key, array_keys($rows[0]));

            return CsvResponse::stream($report.'.csv', $headers, collect($rows)->map(static fn (array $row): array => array_map(static fn ($value) => is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : $value, array_values($row))));
        }

        return XlsxResponse::download($report, $rows);
    }
}
