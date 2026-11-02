<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\Service;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\OrderItem;
use App\Support\Contracts\ReceiptPrinter;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

final class ReceiptController extends ApiController
{
    public function __invoke(Order $order, ReceiptPrinter $printer): JsonResponse
    {
        $this->authorize('view', $order);
        $order->load(['branch', 'items.itemable']);
        $path = $printer->print([
            'number' => $order->number, 'branch' => $order->branch?->name,
            'total' => $order->total->toString(), 'paid' => $order->paid->toString(), 'debt' => $order->debt->toString(),
            'items' => $order->items->map(function (OrderItem $item): array {
                $model = $item->itemable;

                return ['name' => $model instanceof ProductVariant ? $model->product?->name : ($model instanceof Service ? $model->name : ''),
                    'quantity' => $item->quantity, 'total' => $item->total->toString()];
            })->all(),
        ]);

        return ApiResponse::data(['html' => Storage::disk('local')->get($path), 'is_fiscal' => $printer->isFiscal()]);
    }
}
