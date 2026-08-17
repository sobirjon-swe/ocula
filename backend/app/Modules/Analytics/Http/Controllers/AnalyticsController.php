<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Http\Controllers;

use App\Modules\Analytics\Services\AbcAnalysisService;
use App\Modules\Analytics\Services\BranchRankingService;
use App\Modules\Analytics\Services\CashReportService;
use App\Modules\Analytics\Services\DashboardService;
use App\Modules\Analytics\Services\DeadStockReportService;
use App\Modules\Analytics\Services\LostSaleReportService;
use App\Modules\Analytics\Services\ProfitReportService;
use App\Modules\Analytics\Services\StaffPerformanceReportService;
use App\Modules\Core\Models\User;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use App\Support\Http\CsvResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Hisobotlar — PERMISSIONS.md §10, BOSQICH-10.md §10c.
 *
 * Har biri o'z ruxsati bilan cheklangan (faqat `dashboard` bundan
 * mustasno — u hammada bor). `?format=csv` — har bir endpointda
 * eksport.
 */
final class AnalyticsController extends ApiController
{
    public function dashboard(Request $request, DashboardService $service): JsonResponse
    {
        $user = $this->currentUser($request);
        $this->authorizePermission($user, 'analytics.dashboard.view');

        return ApiResponse::data($service->summary($user, $request->integer('branch_id') ?: null));
    }

    public function cashReport(Request $request, CashReportService $service): JsonResponse|StreamedResponse
    {
        $user = $this->currentUser($request);
        $this->authorizePermission($user, 'analytics.cash_report.view');

        [$from, $to] = $this->dateRange($request);
        $report = $service->generate($user, $from, $to, $request->integer('branch_id') ?: null);

        if ($this->wantsCsv($request)) {
            $user->can('analytics.export') || abort(Response::HTTP_FORBIDDEN);

            return CsvResponse::stream(
                'cash-report.csv',
                ['category', 'net'],
                collect($report['by_category'])->map(fn (array $row): array => [$row['category'], $row['net']]),
            );
        }

        return ApiResponse::data($report);
    }

    public function profitReport(Request $request, ProfitReportService $service): JsonResponse
    {
        $user = $this->currentUser($request);
        $this->authorizePermission($user, 'analytics.profit_report.view');

        [$from, $to] = $this->dateRange($request);

        return ApiResponse::data($service->generate($user, $from, $to, $request->integer('branch_id') ?: null));
    }

    public function abcAnalysis(Request $request, AbcAnalysisService $service): JsonResponse|StreamedResponse
    {
        $user = $this->currentUser($request);
        $this->authorizePermission($user, 'analytics.stock_report.view');

        [$from, $to] = $this->dateRange($request);
        $rows = $service->generate($user, $from, $to, $request->integer('branch_id') ?: null);

        if ($this->wantsCsv($request)) {
            $user->can('analytics.export') || abort(Response::HTTP_FORBIDDEN);

            return CsvResponse::stream(
                'abc-analysis.csv',
                ['variant_id', 'revenue', 'quantity', 'cumulative_percent', 'tier'],
                collect($rows)->map(fn (array $row): array => array_values($row)),
            );
        }

        return ApiResponse::data($rows);
    }

    public function deadStock(Request $request, DeadStockReportService $service): JsonResponse
    {
        $user = $this->currentUser($request);
        $this->authorizePermission($user, 'analytics.stock_report.view');

        $days = $request->integer('days') ?: 90;

        return ApiResponse::data(
            $service->generate($user, $request->integer('branch_id') ?: null, $days),
        );
    }

    public function lostSaleReport(Request $request, LostSaleReportService $service): JsonResponse
    {
        $user = $this->currentUser($request);
        $this->authorizePermission($user, 'warehouse.lost_sale.view_any');

        [$from, $to] = $this->dateRange($request);

        return ApiResponse::data($service->generate($user, $from, $to, $request->integer('branch_id') ?: null));
    }

    public function staffReport(Request $request, StaffPerformanceReportService $service): JsonResponse
    {
        $user = $this->currentUser($request);
        $this->authorizePermission($user, 'analytics.staff_report.view');

        $period = $request->string('period')->toString() ?: CarbonImmutable::today()->format('Y-m');

        return ApiResponse::data(
            $service->generate($user, $period, $request->integer('branch_id') ?: null),
        );
    }

    public function branchRating(Request $request, BranchRankingService $service): JsonResponse
    {
        $user = $this->currentUser($request);
        $this->authorizePermission($user, 'analytics.branch_rating.view');

        $period = $request->string('period')->toString() ?: CarbonImmutable::today()->format('Y-m');

        return ApiResponse::data($service->generate($user, $period));
    }

    private function authorizePermission(User $user, string $permission): void
    {
        if (! $user->can($permission)) {
            abort(Response::HTTP_FORBIDDEN);
        }
    }

    private function wantsCsv(Request $request): bool
    {
        return $request->string('format')->toString() === 'csv';
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function dateRange(Request $request): array
    {
        $to = $request->filled('to')
            ? CarbonImmutable::parse($request->string('to')->toString())
            : CarbonImmutable::today();

        $from = $request->filled('from')
            ? CarbonImmutable::parse($request->string('from')->toString())
            : $to->startOfMonth();

        return [$from, $to];
    }
}
