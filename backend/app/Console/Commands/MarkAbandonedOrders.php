<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class MarkAbandonedOrders extends Command
{
    protected $signature = 'sales:mark-abandoned';

    protected $description = 'Mark ready orders uncollected for 30 days and record customer history';

    public function handle(): int
    {
        $count = 0;
        Order::withoutGlobalScopes()->whereIn('status', ['ready', 'customer_notified'])
            ->whereNull('abandoned_at')->where('status_changed_at', '<=', now()->subDays((int) config('optika.orders.abandoned_after_days', 30)))
            ->chunkById(100, function ($orders) use (&$count): void {
                foreach ($orders as $order) {
                    DB::transaction(function () use ($order, &$count): void {
                        $row = Order::withoutGlobalScopes()->lockForUpdate()->findOrFail($order->id);
                        if ($row->abandoned_at !== null || ! in_array($row->status->value, ['ready', 'customer_notified'], true)) {
                            return;
                        }
                        $row->update(['abandoned_at' => now()]);
                        if ($row->customer_id !== null) {
                            Customer::query()->whereKey($row->customer_id)->increment('abandoned_orders_count');
                        }
                        $count++;
                    });
                }
            });
        $this->info('Marked: '.$count);

        return self::SUCCESS;
    }
}
