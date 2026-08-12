<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Controllers;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Location;
use App\Modules\Warehouse\Actions\Purchase\CreatePurchase;
use App\Modules\Warehouse\Actions\Purchase\ReceivePurchase;
use App\Modules\Warehouse\Enums\PurchaseStatus;
use App\Modules\Warehouse\Http\Requests\Purchase\StorePurchaseRequest;
use App\Modules\Warehouse\Http\Resources\PurchaseResource;
use App\Modules\Warehouse\Models\Purchase;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Kirim hujjatlari — PROJECT.md 7.20, SCHEMA.md §3.
 *
 *     draft → received   (FIFO qatlamlari ochiladi)
 *     draft → cancelled
 *
 * `received` dan orqaga qaytish yo'q — xato bo'lsa harakat storno
 * qilinadi (7.21), shuning uchun bu yerda `update` va `destroy` yo'q.
 *
 * Ro'yxat `BranchScope` bilan avtomatik cheklanadi.
 */
final class PurchaseController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Purchase::class);

        $purchases = QueryBuilder::for(Purchase::class)
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::exact('branch_id'),
                AllowedFilter::partial('number'),
            )
            ->allowedSorts('date', 'number', 'total', 'created_at')
            ->defaultSort('-date', '-id')
            ->with('supplier')
            ->withCount('items')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return PurchaseResource::collection($purchases);
    }

    public function show(Purchase $purchase): PurchaseResource
    {
        $this->authorize('view', $purchase);

        return new PurchaseResource($purchase->load(['supplier', 'items.variant']));
    }

    public function store(StorePurchaseRequest $request, CreatePurchase $create): JsonResponse
    {
        $this->authorize('create', Purchase::class);

        $branch = Branch::findOrFail($request->integer('branch_id'));
        $location = Location::findOrFail($request->integer('location_id'));

        $actor = $this->currentUser($request);

        if (! $actor->canAccessAllBranches() && ! in_array($branch->id, $actor->accessibleBranchIds(), true)) {
            throw ValidationException::withMessages([
                'branch_id' => __('core::shift.foreign_branch'),
            ]);
        }

        /** @var array<int, array{variant_id: int, quantity: int, cost_price: string}> $items */
        $items = array_map(
            static fn (array $row): array => [
                'variant_id' => (int) $row['variant_id'],
                'quantity' => (int) $row['quantity'],
                'cost_price' => (string) $row['cost_price'],
            ],
            $request->array('items'),
        );

        $purchase = $create->handle(
            $actor,
            $branch,
            $location,
            $items,
            $request->string('date')->toString(),
            $request->integer('supplier_id'),
            $request->string('note')->toString() ?: null,
        );

        return ApiResponse::created(
            (new PurchaseResource($purchase->load(['supplier', 'items'])))->resolve($request),
        );
    }

    /**
     * Qabul qilish — shu paytda ombor birinchi marta o'zgaradi (7.20).
     */
    public function receive(Request $request, Purchase $purchase, ReceivePurchase $receive): PurchaseResource
    {
        $this->authorize('receive', $purchase);

        $received = $receive->handle($this->currentUser($request), $purchase);

        return new PurchaseResource($received->load(['supplier', 'items.variant']));
    }

    /**
     * Bekor qilish — faqat qoralama (ENUMS.md §3).
     */
    public function cancel(Purchase $purchase): PurchaseResource
    {
        $this->authorize('cancel', $purchase);

        if ($purchase->status !== PurchaseStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => __('warehouse::purchase.cancel_only_draft'),
            ]);
        }

        $purchase->update(['status' => PurchaseStatus::Cancelled]);

        return new PurchaseResource($purchase);
    }
}
