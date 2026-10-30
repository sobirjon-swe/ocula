<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\Branch;
use App\Modules\Finance\Models\Debt;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

final class OperationalReportsTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    #[Test]
    public function debt_aging_uses_due_dates_and_remaining_amounts(): void
    {
        $this->seedPermissions();
        $this->actingAsDirector();
        $branch = Branch::factory()->create();
        foreach ([3, 15, 60, 100] as $days) {
            $customer = Customer::factory()->create();
            $order = Order::factory()->create(['branch_id' => $branch->id, 'customer_id' => $customer->id]);
            Debt::create(['branch_id' => $branch->id, 'customer_id' => $customer->id, 'order_id' => $order->id, 'amount' => '200000', 'paid' => '50000', 'due_date' => now('Asia/Tashkent')->subDays($days)->toDateString(), 'status' => 'overdue']);
        }
        $this->getJson('/api/v1/analytics/debt-aging')->assertOk()->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.remaining', '150000.00')->assertJsonPath('data.3.bucket', '90+');
    }

    #[Test]
    public function operational_reports_are_permission_protected(): void
    {
        $this->seedPermissions();
        $this->actingAsEmployee(Role::Seller, Branch::factory()->create());
        $this->getJson('/api/v1/analytics/turnover')->assertForbidden();
        $this->getJson('/api/v1/analytics/supplier-balances')->assertForbidden();
    }

    #[Test]
    public function a_director_downloads_a_real_xlsx_workbook(): void
    {
        $this->seedPermissions();
        $this->actingAsDirector();
        $response = $this->get('/api/v1/analytics/exports/profit-report')->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('Content-Type'));
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $file = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($file));
        $this->assertNotFalse($zip->getFromName('xl/workbook.xml'));
        $zip->close();
        unlink($file);
    }
}
