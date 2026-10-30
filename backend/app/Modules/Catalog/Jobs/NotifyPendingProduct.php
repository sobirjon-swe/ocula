<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Jobs;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Telegram\Services\StaffNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

final class NotifyPendingProduct implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly int $productId) {}

    public function handle(StaffNotifier $notifier): void
    {
        $product = Product::find($this->productId);
        if (! $product instanceof Product || $product->status !== ProductStatus::Pending) {
            return;
        }
        if (! $notifier->director('pending-product:'.$product->id, 'catalog::staff.pending_product', ['id' => $product->id, 'name' => $product->name])) {
            throw new RuntimeException('Staff notification delivery failed.');
        }
    }
}
