<?php

declare(strict_types=1);

namespace App\Modules\Sales;

use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\OrderReturn;
use App\Modules\Sales\Models\Payment;
use App\Modules\Sales\Policies\CustomerPolicy;
use App\Modules\Sales\Policies\OrderPolicy;
use App\Modules\Sales\Policies\OrderReturnPolicy;
use App\Modules\Sales\Policies\PaymentPolicy;
use App\Support\Providers\ModuleServiceProvider;
use Illuminate\Support\Facades\Route;

/**
 * Sales — chek, buyurtma, to'lov, qaytarish, chegirma, mijozlar.
 *
 * PROJECT.md §6.4, 7.3 (ikkita alohida holat o'qi), 7.6 (qarz),
 * 7.8 (daromad tan olinishi), 7.13 (sotuv paytida katalogga qo'shish).
 */
final class SalesServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Sales';
    }

    /**
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [
            Customer::class => CustomerPolicy::class,
            Order::class => OrderPolicy::class,
            Payment::class => PaymentPolicy::class,
            OrderReturn::class => OrderReturnPolicy::class,
        ];
    }

    public function boot(): void
    {
        // `returns` yo'lidagi `{return}` parametri `OrderReturn` ga
        // bog'lanadi: `Return` PHP kalit so'zi, model shunday nomlanmagan.
        Route::model('return', OrderReturn::class);

        parent::boot();
    }
}
