<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Http\Controllers;

use App\Modules\Analytics\Services\OperationalReportService;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use App\Support\Http\CsvResponse;
use App\Support\Http\XlsxResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class OperationalReportController extends ApiController
{
    public function __invoke(Request $request, string $report, OperationalReportService $service): Response
    {
        $user = $this->currentUser($request);
        $permission = match ($report) {
            'debt-aging' => 'finance.debt.view_any', 'turnover' => 'analytics.stock_report.view',
            'abandoned-orders' => 'analytics.profit_report.view', 'transport-costs' => 'analytics.cash_report.view',
            'supplier-balances' => 'finance.supplier.balance.view', default => '',
        };
        abort_unless($permission !== '' && $user->can($permission), 403);
        $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'], 'branch_id' => ['nullable', 'integer']]);
        $to = CarbonImmutable::parse($request->input('to') ?: CarbonImmutable::today('Asia/Tashkent')->toDateString(), 'Asia/Tashkent');
        $from = CarbonImmutable::parse($request->input('from') ?: $to->startOfMonth()->toDateString(), 'Asia/Tashkent');
        $rows = $service->generate($report, $user, $from, $to, $request->integer('branch_id') ?: null);
        if ($request->input('format') === 'csv') {
            abort_unless($user->can('analytics.export'), 403);
            $headers = $rows === [] ? [] : array_keys($rows[0]);

            return CsvResponse::stream($report.'.csv', $headers, collect($rows)->map(fn (array $row): array => array_values($row)));
        }
        if ($request->input('format') === 'xlsx') {
            abort_unless($user->can('analytics.export'), 403);

            return XlsxResponse::download($report, $rows);
        }

        return ApiResponse::data($rows);
    }
}
