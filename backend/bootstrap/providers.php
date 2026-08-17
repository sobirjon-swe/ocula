<?php

use App\Modules\Analytics\AnalyticsServiceProvider;
use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Clinic\ClinicServiceProvider;
use App\Modules\Core\CoreServiceProvider;
use App\Modules\Customer\CustomerServiceProvider;
use App\Modules\Delivery\DeliveryServiceProvider;
use App\Modules\Finance\FinanceServiceProvider;
use App\Modules\Payroll\PayrollServiceProvider;
use App\Modules\Sales\SalesServiceProvider;
use App\Modules\Telegram\TelegramServiceProvider;
use App\Modules\Warehouse\WarehouseServiceProvider;
use App\Modules\Workshop\WorkshopServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,

    // Modullar — PROJECT.md §3. Tartib muhim emas, har biri mustaqil.
    CoreServiceProvider::class,
    CatalogServiceProvider::class,
    WarehouseServiceProvider::class,
    SalesServiceProvider::class,
    ClinicServiceProvider::class,
    WorkshopServiceProvider::class,
    DeliveryServiceProvider::class,
    FinanceServiceProvider::class,
    PayrollServiceProvider::class,
    CustomerServiceProvider::class,
    AnalyticsServiceProvider::class,
    TelegramServiceProvider::class,
];
