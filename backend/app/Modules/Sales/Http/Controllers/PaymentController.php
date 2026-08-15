<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Sales\Actions\Payment\RecordPayment;
use App\Modules\Sales\Actions\Payment\ReversePayment;
use App\Modules\Sales\Enums\PaymentMethod;
use App\Modules\Sales\Http\Requests\Payment\ReversePaymentRequest;
use App\Modules\Sales\Http\Requests\Payment\StorePaymentRequest;
use App\Modules\Sales\Http\Resources\PaymentResource;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\Payment;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * To'lovlar — PROJECT.md 7.8-A, 7.21.
 *
 * Yozuvlar insert-only: tahrirlash ham, o'chirish ham yo'q, faqat
 * storno. Shuning uchun bu yerda `update`/`destroy` yo'q.
 */
final class PaymentController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Payment::class);

        $payments = QueryBuilder::for(Payment::class)
            ->allowedFilters(
                AllowedFilter::exact('order_id'),
                AllowedFilter::exact('customer_id'),
                AllowedFilter::exact('branch_id'),
                AllowedFilter::exact('shift_id'),
                AllowedFilter::exact('method'),
                AllowedFilter::exact('status'),
            )
            ->allowedSorts('paid_at', 'amount')
            ->defaultSort('-paid_at', '-id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return PaymentResource::collection($payments);
    }

    /**
     * To'lov qabul qilish. Naqd bo'lsa kassa daftariga ham tushadi,
     * karta va o'tkazma — tushmaydi (ENUMS.md §4).
     */
    public function store(
        StorePaymentRequest $request,
        Order $order,
        RecordPayment $record,
    ): JsonResponse {
        $this->authorize('create', Payment::class);
        $this->authorize('view', $order);

        $payment = $record->handle(
            $this->currentUser($request),
            $order,
            Money::of($request->string('amount')->toString()),
            PaymentMethod::from($request->string('method')->toString()),
        );

        return ApiResponse::created((new PaymentResource($payment))->resolve($request));
    }

    /**
     * Storno — faqat direktor (`sales.payment.reverse`).
     */
    public function reverse(
        ReversePaymentRequest $request,
        Payment $payment,
        ReversePayment $reverse,
    ): JsonResponse {
        $this->authorize('reverse', $payment);

        $reversal = $reverse->handle(
            $this->currentUser($request),
            $payment,
            $request->string('reason')->toString(),
        );

        return ApiResponse::created((new PaymentResource($reversal))->resolve($request));
    }
}
