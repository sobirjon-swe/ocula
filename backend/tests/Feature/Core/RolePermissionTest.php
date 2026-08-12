<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Rol → ruxsat matritsasi — docs/PERMISSIONS.md.
 *
 * Bu testlar hujjatdagi "Muhim nozikliklar" bo'limini qo'riqlaydi:
 * ular biznes qarorlari, tasodifiy sozlama emas.
 */
final class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    #[Test]
    public function a_seller_cannot_see_cost_prices(): void
    {
        // Nozikliklar #1: aks holda chegirma berishda tannarxga qarab
        // savdolashadi.
        $this->assertFalse($this->userWith(Role::Seller)->can('catalog.cost.view'));
        $this->assertTrue($this->userWith(Role::BranchManager)->can('catalog.cost.view'));
    }

    #[Test]
    public function only_the_director_can_reverse_financial_records(): void
    {
        // Nozikliklar #4: barcha storno amallari faqat direktorda (7.21).
        foreach (['warehouse.movement.reverse', 'sales.payment.reverse', 'finance.cash_movement.reverse'] as $permission) {
            $this->assertTrue($this->userWith(Role::Director)->can($permission), $permission);

            foreach ([Role::BranchManager, Role::Seller, Role::Accountant, Role::Warehouse] as $role) {
                $this->assertFalse($this->userWith($role)->can($permission), "{$role->value} / {$permission}");
            }
        }
    }

    #[Test]
    public function only_a_doctor_can_write_prescriptions(): void
    {
        // Nozikliklar #2: tibbiy javobgarlik. Direktor o'qiy oladi, yoza olmaydi.
        $this->assertTrue($this->userWith(Role::Doctor)->can('clinic.prescription.create'));
        $this->assertFalse($this->userWith(Role::Director)->can('clinic.prescription.create'));
        $this->assertTrue($this->userWith(Role::Director)->can('clinic.prescription.view_history'));
    }

    #[Test]
    public function a_driver_hands_cash_over_but_never_receives_it(): void
    {
        // Nozikliklar #3: ikki tomonlama nazorat.
        $driver = $this->userWith(Role::Driver);

        $this->assertTrue($driver->can('delivery.collection.create'));
        $this->assertFalse($driver->can('delivery.collection.receive'));
        $this->assertTrue($this->userWith(Role::Accountant)->can('delivery.collection.receive'));
    }

    #[Test]
    public function every_staff_role_can_see_their_own_bonus(): void
    {
        // Nozikliklar #5: motivatsiya uchun muhim.
        foreach (Role::staff() as $role) {
            $this->assertTrue($this->userWith($role)->can('payroll.bonus.view_own'), $role->value);
        }
    }

    #[Test]
    public function only_director_and_accountant_may_bypass_the_branch_scope(): void
    {
        foreach (['core.shift.view_all_branches', 'sales.order.view_all_branches', 'warehouse.stock.view_all_branches'] as $permission) {
            $this->assertTrue($this->userWith(Role::Director)->can($permission), $permission);
            $this->assertTrue($this->userWith(Role::Accountant)->can($permission), $permission);
            $this->assertFalse($this->userWith(Role::Seller)->can($permission), $permission);
            $this->assertFalse($this->userWith(Role::BranchManager)->can($permission), $permission);
        }
    }

    #[Test]
    public function a_seller_may_add_a_product_while_selling_but_not_approve_it(): void
    {
        // 7.13 + 7.17: sotuvchi katalogni to'ldiradi, direktor tasdiqlaydi.
        $seller = $this->userWith(Role::Seller);

        $this->assertTrue($seller->can('catalog.product.quick_create'));
        $this->assertFalse($seller->can('catalog.product.approve'));
        $this->assertTrue($this->userWith(Role::Director)->can('catalog.product.approve'));
    }

    private function userWith(Role $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role->value]);

        return $user->fresh() ?? $user;
    }
}
