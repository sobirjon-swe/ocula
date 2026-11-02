<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Observers;

use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Jobs\NotifyPendingProduct;
use App\Modules\Catalog\Models\Product;

final class PendingProductObserver
{
    public function created(Product $product): void
    {
        if ($product->status === ProductStatus::Pending && config('services.telegram.director_chat_id')) {
            NotifyPendingProduct::dispatch($product->id)->afterCommit();
        }
    }
}
