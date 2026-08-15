<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Controllers;

use App\Modules\Core\Models\Location;
use App\Modules\Warehouse\Actions\Transfer\CancelTransfer;
use App\Modules\Warehouse\Actions\Transfer\CreateTransfer;
use App\Modules\Warehouse\Actions\Transfer\ReceiveTransfer;
use App\Modules\Warehouse\Actions\Transfer\ResolveDiscrepancy;
use App\Modules\Warehouse\Actions\Transfer\SendTransfer;
use App\Modules\Warehouse\Enums\DeliveryMethod;
use App\Modules\Warehouse\Http\Requests\Transfer\ReceiveTransferRequest;
use App\Modules\Warehouse\Http\Requests\Transfer\ResolveDiscrepancyRequest;
use App\Modules\Warehouse\Http\Requests\Transfer\SendTransferRequest;
use App\Modules\Warehouse\Http\Requests\Transfer\StoreTransferRequest;
use App\Modules\Warehouse\Http\Resources\TransferResource;
use App\Modules\Warehouse\Models\Transfer;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Transferlar — PROJECT.md 7.4, 7.10.
 *
 *     draft → sent → received | partially_received
 *     draft → cancelled
 *
 * Har o'tish o'z endpoint'i va o'z ruxsati bilan: jo'natgan odam o'zi
 * qabul qilib, yo'ldagi kamomadni o'zi yopib qo'ymasligi kerak.
 *
 * Ro'yxat `TwoSidedBranchScope` bilan cheklanadi — jo'natuvchi ham,
 * qabul qiluvchi ham o'z transferini ko'radi.
 */
final class TransferController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Transfer::class);

        $transfers = QueryBuilder::for(Transfer::class)
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('delivery_method'),
                AllowedFilter::exact('from_location_id'),
                AllowedFilter::exact('to_location_id'),
                AllowedFilter::exact('has_discrepancy'),
                AllowedFilter::partial('number'),
            )
            ->allowedSorts('created_at', 'number', 'sent_at', 'received_at')
            ->defaultSort('-created_at', '-id')
            ->withCount('items')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return TransferResource::collection($transfers);
    }

    public function show(Transfer $transfer): TransferResource
    {
        $this->authorize('view', $transfer);

        return new TransferResource($transfer->load('items.variant'));
    }

    public function store(StoreTransferRequest $request, CreateTransfer $create): JsonResponse
    {
        $this->authorize('create', Transfer::class);

        // Jo'natuvchi ombor **scope bilan** qidiriladi: begona filial
        // omborini ko'rsatgan so'rov 404 oladi, 403 emas — 403 "bunday
        // ombor bor" degan ma'lumotni oshkor qilardi.
        $from = Location::findOrFail($request->integer('from_location_id'));

        // Qabul qiluvchi ombor esa ataylab scope'siz: u boshqa filialda
        // bo'lishi transferning butun mohiyati.
        $to = Location::withoutGlobalScopes()->findOrFail($request->integer('to_location_id'));

        $actor = $this->currentUser($request);

        /** @var array<int, array{variant_id: int, quantity: int}> $items */
        $items = array_map(
            static fn (array $row): array => [
                'variant_id' => (int) $row['variant_id'],
                'quantity' => (int) $row['quantity'],
            ],
            $request->array('items'),
        );

        $transfer = $create->handle(
            $actor,
            $from,
            $to,
            $items,
            $request->string('note')->toString() ?: null,
        );

        return ApiResponse::created(
            (new TransferResource($transfer->load('items')))->resolve($request),
        );
    }

    /**
     * Jo'natish — tovar shu paytda ombordan chiqib transitga o'tadi.
     */
    public function send(SendTransferRequest $request, Transfer $transfer, SendTransfer $send): TransferResource
    {
        $this->authorize('send', $transfer);

        $taxiCost = $request->string('taxi_cost')->toString();

        $sent = $send->handle(
            $this->currentUser($request),
            $transfer,
            DeliveryMethod::from($request->string('delivery_method')->toString()),
            $request->integer('carrier_id') ?: null,
            $taxiCost === '' ? null : Money::of($taxiCost),
            $request->string('taxi_receipt_path')->toString() ?: null,
        );

        return new TransferResource($sent->load('items'));
    }

    /**
     * Qabul qilish — xodim haqiqiy miqdorni kiritadi (7.4).
     */
    public function receive(
        ReceiveTransferRequest $request,
        Transfer $transfer,
        ReceiveTransfer $receive,
    ): TransferResource {
        $this->authorize('receive', $transfer);

        /** @var array<int, array{item_id: int, quantity: int}> $items */
        $items = array_map(
            static fn (array $row): array => [
                'item_id' => (int) $row['item_id'],
                'quantity' => (int) $row['quantity'],
            ],
            $request->array('items'),
        );

        $received = $receive->handle($this->currentUser($request), $transfer, $items);

        return new TransferResource($received->load('items'));
    }

    public function cancel(Transfer $transfer, CancelTransfer $cancel): TransferResource
    {
        $this->authorize('cancel', $transfer);

        return new TransferResource($cancel->handle($transfer));
    }

    /**
     * Miqdor farqini hal qilish — omborga tegmaydi, ochiq savolni
     * yopadi (7.4).
     */
    public function resolveDiscrepancy(
        ResolveDiscrepancyRequest $request,
        Transfer $transfer,
        ResolveDiscrepancy $resolve,
    ): TransferResource {
        $this->authorize('resolveDiscrepancy', $transfer);

        $resolved = $resolve->handle(
            $this->currentUser($request),
            $transfer,
            $request->string('resolution')->toString(),
        );

        return new TransferResource($resolved->load('items'));
    }
}
