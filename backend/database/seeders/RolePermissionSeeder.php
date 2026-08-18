<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rollar va ruxsatlar — docs/PERMISSIONS.md.
 *
 * Bosqich 1 faqat `core` + `catalog` bilan ishlaydi, lekin bu seeder
 * **butun ro'yxatni** yaratadi: keyingi bosqichda ekran qo'shilganda
 * ruxsat allaqachon tayyor turadi, faqat Policy va Controller yoziladi.
 *
 * `customer` guard ruxsatlari (PERMISSIONS.md §12) bu yerda **yo'q** —
 * ular `customers` jadvali bilan birga Customer modulida keladi.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * Barcha ruxsatlar, modul bo'yicha guruhlangan.
     *
     * @var array<string, array<int, string>>
     */
    private const array PERMISSIONS = [
        'core' => [
            'core.branch.view_any', 'core.branch.view', 'core.branch.create',
            'core.branch.update', 'core.branch.delete',
            'core.user.view_any', 'core.user.view', 'core.user.create',
            'core.user.update', 'core.user.delete', 'core.user.assign_role',
            'core.user.set_debt_limit', 'core.user.set_pin',
            'core.device.view_any', 'core.device.register', 'core.device.revoke',
            'core.shift.view_any', 'core.shift.view_all_branches', 'core.shift.open',
            'core.shift.close', 'core.shift.approve_difference',
            'core.settings.view', 'core.settings.update',
        ],
        'catalog' => [
            'catalog.product.view_any', 'catalog.product.view', 'catalog.product.create',
            'catalog.product.quick_create', 'catalog.product.update', 'catalog.product.delete',
            'catalog.product.approve', 'catalog.product.merge',
            'catalog.variant.create', 'catalog.variant.update',
            'catalog.price.view', 'catalog.price.update',
            'catalog.cost.view',
            'catalog.brand.manage', 'catalog.category.manage', 'catalog.service.manage',
        ],
        'warehouse' => [
            'warehouse.stock.view', 'warehouse.stock.view_all_branches',
            'warehouse.movement.view_any', 'warehouse.movement.reverse',
            'warehouse.purchase.view_any', 'warehouse.purchase.create',
            'warehouse.purchase.receive', 'warehouse.purchase.cancel',
            'warehouse.transfer.view_any', 'warehouse.transfer.create',
            'warehouse.transfer.send', 'warehouse.transfer.receive',
            'warehouse.transfer.cancel', 'warehouse.transfer.resolve_discrepancy',
            'warehouse.request.create', 'warehouse.request.approve', 'warehouse.request.fulfill',
            'warehouse.inventory.view_any', 'warehouse.inventory.start',
            'warehouse.inventory.count', 'warehouse.inventory.review',
            'warehouse.inventory.complete',
            'warehouse.defect.report', 'warehouse.defect.view_any', 'warehouse.defect.approve',
            'warehouse.adjustment.create',
            'warehouse.lost_sale.create', 'warehouse.lost_sale.view_any',
            'warehouse.master_stock.view', 'warehouse.master_stock.consume',
        ],
        'sales' => [
            'sales.order.view_any', 'sales.order.view_all_branches', 'sales.order.create',
            'sales.order.update', 'sales.order.cancel', 'sales.order.change_status',
            'sales.order.deliver', 'sales.order.rework',
            'sales.discount.apply', 'sales.discount.approve',
            'sales.payment.create', 'sales.payment.reverse',
            'sales.return.create', 'sales.return.approve',
            'sales.customer.view_any', 'sales.customer.view', 'sales.customer.create',
            'sales.customer.update', 'sales.customer.debt.view', 'sales.customer.debt.grant',
            'sales.receipt.print',
        ],
        'clinic' => [
            'clinic.visit.view_any', 'clinic.visit.create', 'clinic.visit.start',
            'clinic.visit.finish', 'clinic.visit.cancel',
            'clinic.prescription.create', 'clinic.prescription.update',
            'clinic.prescription.view', 'clinic.prescription.view_history',
            'clinic.ticket.transfer_branch',
            'clinic.appointment_request.view_any', 'clinic.appointment_request.manage',
        ],
        'workshop' => [
            'workshop.work_order.view_any', 'workshop.work_order.assign',
            'workshop.work_order.start', 'workshop.work_order.finish',
            'workshop.work_order.defect', 'workshop.work_order.set_priority',
        ],
        'delivery' => [
            'delivery.trip.view_any', 'delivery.trip.create', 'delivery.trip.start',
            'delivery.trip.finish',
            'delivery.stop.deliver', 'delivery.stop.fail',
            'delivery.balance.view', 'delivery.balance.view_any',
            'delivery.collection.create', 'delivery.collection.receive',
        ],
        'finance' => [
            'finance.cash.view', 'finance.cash.view_all_branches',
            'finance.cash_movement.create', 'finance.cash_movement.reverse',
            'finance.expense.view_any', 'finance.expense.create', 'finance.expense.approve',
            'finance.expense_category.manage',
            'finance.debt.view_any', 'finance.debt.approve', 'finance.debt.write_off',
            'finance.debt.remind',
            'finance.supplier.view_any', 'finance.supplier.manage',
            'finance.supplier.balance.view', 'finance.supplier_payment.create',
        ],
        'payroll' => [
            'payroll.bonus.view_own', 'payroll.bonus.view_any',
            'payroll.bonus.approve', 'payroll.bonus.pay',
            'payroll.bonus_rule.view', 'payroll.bonus_rule.manage',
            'payroll.plan.view', 'payroll.plan.manage',
        ],
        'analytics' => [
            'analytics.dashboard.view', 'analytics.cash_report.view',
            'analytics.profit_report.view', 'analytics.stock_report.view',
            'analytics.staff_report.view', 'analytics.branch_rating.view',
            'analytics.export',
        ],
        'admin' => [
            'admin.activity_log.view', 'admin.horizon.access', 'admin.impersonate',
        ],
    ];

    /**
     * Direktorda YO'Q ruxsatlar — PERMISSIONS.md "Muhim nozikliklar".
     *
     * Retseptni faqat shifokor yozadi (tibbiy javobgarlik), yetkazish va
     * pul topshirish esa haydovchining ishi.
     *
     * @var array<int, string>
     */
    private const array DIRECTOR_EXCLUDES = [
        'clinic.prescription.create',
        'clinic.prescription.update',
        'delivery.stop.deliver',
        'delivery.stop.fail',
        'delivery.collection.create',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::allPermissions() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Yangi yaratilgan ruxsatlar keshda yo'q — `syncPermissions()`
        // ularni topa olmasligi uchun keshni shu yerda ham tozalaymiz.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RoleEnum::staff() as $roleEnum) {
            $role = Role::findOrCreate($roleEnum->value, 'web');
            $role->syncPermissions($this->permissionsFor($roleEnum));
        }

        // `customer` — alohida guard (§4). Roli Customer moduli bilan keladi.

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (! App::runningUnitTests()) {
            $this->command?->info('Rollar va ruxsatlar yaratildi: '.count(self::allPermissions()).' ta ruxsat.');
        }
    }

    /**
     * @return array<int, string>
     */
    private static function allPermissions(): array
    {
        return array_merge(...array_values(self::PERMISSIONS));
    }

    /**
     * @return array<int, string>
     */
    private function permissionsFor(RoleEnum $role): array
    {
        return match ($role) {
            RoleEnum::Director => array_values(array_diff(self::allPermissions(), self::DIRECTOR_EXCLUDES)),
            RoleEnum::BranchManager => $this->branchManager(),
            RoleEnum::Seller => $this->seller(),
            RoleEnum::Doctor => $this->doctor(),
            RoleEnum::Master => $this->master(),
            RoleEnum::Warehouse => $this->warehouse(),
            RoleEnum::Driver => $this->driver(),
            RoleEnum::Accountant => $this->accountant(),
            RoleEnum::Customer => [],
        };
    }

    /**
     * @return array<int, string>
     */
    private function branchManager(): array
    {
        return [
            'core.branch.view',
            'core.user.view_any', 'core.user.view',
            'core.device.view_any', 'core.device.register', 'core.device.revoke',
            'core.shift.view_any', 'core.shift.open', 'core.shift.close',
            'core.shift.approve_difference',
            'core.settings.view',
            'catalog.product.view_any', 'catalog.product.view', 'catalog.product.create',
            'catalog.product.quick_create', 'catalog.product.update',
            'catalog.variant.create', 'catalog.variant.update',
            'catalog.price.view', 'catalog.price.update', 'catalog.cost.view',
            'warehouse.stock.view', 'warehouse.movement.view_any',
            'warehouse.purchase.view_any', 'warehouse.purchase.create',
            'warehouse.purchase.receive', 'warehouse.purchase.cancel',
            'warehouse.transfer.view_any', 'warehouse.transfer.create',
            'warehouse.transfer.send', 'warehouse.transfer.receive',
            'warehouse.transfer.cancel', 'warehouse.transfer.resolve_discrepancy',
            'warehouse.request.create', 'warehouse.request.approve', 'warehouse.request.fulfill',
            'warehouse.inventory.view_any', 'warehouse.inventory.start',
            'warehouse.inventory.count', 'warehouse.inventory.review',
            'warehouse.inventory.complete',
            'warehouse.defect.report', 'warehouse.defect.view_any', 'warehouse.defect.approve',
            'warehouse.lost_sale.create', 'warehouse.lost_sale.view_any',
            'warehouse.master_stock.view',
            'sales.order.view_any', 'sales.order.create', 'sales.order.update',
            'sales.order.cancel', 'sales.order.change_status', 'sales.order.deliver',
            'sales.order.rework',
            'sales.discount.apply', 'sales.payment.create',
            'sales.return.create', 'sales.return.approve',
            'sales.customer.view_any', 'sales.customer.view', 'sales.customer.create',
            'sales.customer.update', 'sales.customer.debt.view', 'sales.customer.debt.grant',
            'sales.receipt.print',
            'clinic.visit.view_any', 'clinic.visit.create', 'clinic.visit.start',
            'clinic.visit.finish', 'clinic.visit.cancel',
            'clinic.prescription.view', 'clinic.prescription.view_history',
            'clinic.ticket.transfer_branch',
            'clinic.appointment_request.view_any', 'clinic.appointment_request.manage',
            'workshop.work_order.view_any', 'workshop.work_order.assign',
            'delivery.trip.view_any', 'delivery.trip.create', 'delivery.trip.start',
            'delivery.trip.finish', 'delivery.balance.view', 'delivery.collection.receive',
            'finance.cash.view', 'finance.expense.view_any', 'finance.expense.create',
            'finance.expense.approve', 'finance.debt.view_any',
            'payroll.bonus.view_own', 'payroll.bonus.view_any',
            'payroll.bonus_rule.view', 'payroll.plan.view',
            'analytics.dashboard.view', 'analytics.cash_report.view',
            'analytics.profit_report.view', 'analytics.stock_report.view',
            'analytics.staff_report.view', 'analytics.branch_rating.view', 'analytics.export',
        ];
    }

    /**
     * Sotuvchida `catalog.cost.view` ataylab YO'Q — aks holda chegirma
     * berishda tannarxga qarab savdolashadi (PERMISSIONS.md nozikliklar #1).
     *
     * @return array<int, string>
     */
    private function seller(): array
    {
        return [
            'core.shift.view_any', 'core.shift.open', 'core.shift.close',
            'catalog.product.view_any', 'catalog.product.view', 'catalog.product.quick_create',
            'catalog.price.view',
            'warehouse.stock.view', 'warehouse.transfer.receive',
            'warehouse.request.create', 'warehouse.inventory.count',
            'warehouse.defect.report', 'warehouse.lost_sale.create',
            'sales.order.view_any', 'sales.order.create', 'sales.order.update',
            'sales.order.change_status', 'sales.order.deliver',
            'sales.discount.apply', 'sales.payment.create', 'sales.return.create',
            'sales.customer.view_any', 'sales.customer.view', 'sales.customer.create',
            'sales.customer.update', 'sales.customer.debt.view', 'sales.customer.debt.grant',
            'sales.receipt.print',
            'clinic.visit.create',
            'clinic.prescription.view', 'clinic.prescription.view_history',
            'workshop.work_order.view_any',
            'delivery.collection.receive',
            'finance.cash.view', 'finance.expense.create', 'finance.debt.view_any',
            'payroll.bonus.view_own',
            'analytics.dashboard.view',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function doctor(): array
    {
        return [
            'catalog.product.view_any', 'catalog.product.view',
            'sales.customer.view',
            'clinic.visit.view_any', 'clinic.visit.create', 'clinic.visit.start',
            'clinic.visit.finish', 'clinic.visit.cancel',
            'clinic.prescription.create', 'clinic.prescription.update',
            'clinic.prescription.view', 'clinic.prescription.view_history',
            'clinic.appointment_request.view_any', 'clinic.appointment_request.manage',
            'payroll.bonus.view_own',
            'analytics.dashboard.view',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function master(): array
    {
        return [
            'catalog.product.view_any', 'catalog.product.view',
            'warehouse.stock.view', 'warehouse.defect.report',
            'warehouse.master_stock.view', 'warehouse.master_stock.consume',
            'sales.order.change_status',
            'clinic.prescription.view',
            'workshop.work_order.view_any', 'workshop.work_order.assign',
            'workshop.work_order.start', 'workshop.work_order.finish',
            'workshop.work_order.defect', 'workshop.work_order.set_priority',
            'payroll.bonus.view_own',
            'analytics.dashboard.view',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function warehouse(): array
    {
        return [
            'core.shift.view_any', 'core.shift.open', 'core.shift.close',
            'catalog.product.view_any', 'catalog.product.view', 'catalog.product.create',
            'catalog.product.quick_create', 'catalog.product.update',
            'catalog.variant.create', 'catalog.variant.update',
            'catalog.price.view', 'catalog.cost.view',
            'warehouse.stock.view', 'warehouse.movement.view_any',
            'warehouse.purchase.view_any', 'warehouse.purchase.create',
            'warehouse.purchase.receive', 'warehouse.purchase.cancel',
            'warehouse.transfer.view_any', 'warehouse.transfer.create',
            'warehouse.transfer.send', 'warehouse.transfer.receive', 'warehouse.transfer.cancel',
            'warehouse.request.create', 'warehouse.request.approve', 'warehouse.request.fulfill',
            'warehouse.inventory.view_any', 'warehouse.inventory.start',
            'warehouse.inventory.count', 'warehouse.inventory.review',
            'warehouse.inventory.complete',
            'warehouse.defect.report', 'warehouse.defect.view_any',
            'warehouse.adjustment.create',
            'warehouse.lost_sale.create', 'warehouse.lost_sale.view_any',
            'warehouse.master_stock.view',
            'delivery.trip.view_any', 'delivery.trip.create',
            'delivery.trip.start', 'delivery.trip.finish',
            'finance.expense.create', 'finance.supplier.view_any',
            'payroll.bonus.view_own',
            'analytics.dashboard.view', 'analytics.stock_report.view', 'analytics.export',
        ];
    }

    /**
     * Haydovchida `delivery.collection.receive` ataylab YO'Q — pulni
     * topshiradi, qabul qilmaydi (ikki tomonlama nazorat, nozikliklar #3).
     *
     * @return array<int, string>
     */
    private function driver(): array
    {
        return [
            'sales.order.deliver', 'sales.payment.create', 'sales.customer.view',
            'delivery.trip.view_any', 'delivery.trip.start', 'delivery.trip.finish',
            'delivery.stop.deliver', 'delivery.stop.fail',
            'delivery.balance.view', 'delivery.collection.create',
            'finance.expense.create',
            'payroll.bonus.view_own',
            'analytics.dashboard.view',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function accountant(): array
    {
        return [
            'core.branch.view', 'core.user.view_any',
            'core.shift.view_any', 'core.shift.view_all_branches',
            'core.shift.open', 'core.shift.close', 'core.settings.view',
            'catalog.product.view_any', 'catalog.product.view',
            'catalog.price.view', 'catalog.cost.view',
            'warehouse.stock.view', 'warehouse.stock.view_all_branches',
            'warehouse.movement.view_any', 'warehouse.purchase.view_any',
            'sales.order.view_any', 'sales.order.view_all_branches',
            'sales.payment.create',
            'sales.customer.view_any', 'sales.customer.view', 'sales.customer.create',
            'sales.customer.update', 'sales.customer.debt.view',
            'delivery.balance.view', 'delivery.balance.view_any',
            'delivery.collection.receive',
            'finance.cash.view', 'finance.cash.view_all_branches',
            'finance.cash_movement.create',
            'finance.expense.view_any', 'finance.expense.create', 'finance.expense.approve',
            'finance.expense_category.manage',
            'finance.debt.view_any', 'finance.debt.remind',
            'finance.supplier.view_any', 'finance.supplier.manage',
            'finance.supplier.balance.view', 'finance.supplier_payment.create',
            'payroll.bonus.view_own', 'payroll.bonus.view_any', 'payroll.bonus.pay',
            'analytics.dashboard.view', 'analytics.cash_report.view',
            'analytics.profit_report.view', 'analytics.stock_report.view',
            'analytics.staff_report.view', 'analytics.branch_rating.view', 'analytics.export',
            'admin.activity_log.view',
        ];
    }
}
