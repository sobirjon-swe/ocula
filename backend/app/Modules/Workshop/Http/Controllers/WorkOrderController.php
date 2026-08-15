<?php

declare(strict_types=1);

namespace App\Modules\Workshop\Http\Controllers;

use App\Modules\Core\Models\Location;
use App\Modules\Warehouse\Enums\DefectReason;
use App\Modules\Warehouse\Http\Resources\DefectResource;
use App\Modules\Workshop\Actions\WorkOrder\ConsumeMaterial;
use App\Modules\Workshop\Actions\WorkOrder\ManageWorkOrder;
use App\Modules\Workshop\Actions\WorkOrder\MarkDefect;
use App\Modules\Workshop\Enums\WorkOrderPriority;
use App\Modules\Workshop\Http\Requests\WorkOrder\AssignMasterRequest;
use App\Modules\Workshop\Http\Requests\WorkOrder\ConsumeMaterialRequest;
use App\Modules\Workshop\Http\Requests\WorkOrder\MarkDefectRequest;
use App\Modules\Workshop\Http\Requests\WorkOrder\SetPriorityRequest;
use App\Modules\Workshop\Http\Resources\WorkOrderResource;
use App\Modules\Workshop\Models\WorkOrder;
use App\Modules\Workshop\Models\WorkOrderItem;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Ustaxona — PROJECT.md §6.6, §10 (usta ekrani).
 *
 *     queued → in_progress → done | defect
 *
 * `store` **yo'q**: buyruq buyurtma `in_workshop` ga o'tganda tizim
 * tomonidan tug'iladi.
 *
 * Ro'yxat `BranchScope` bilan cheklanadi.
 */
final class WorkOrderController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WorkOrder::class);

        $workOrders = QueryBuilder::for(WorkOrder::class)
            ->allowedFilters(
                AllowedFilter::exact('branch_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('priority'),
                AllowedFilter::exact('master_id'),
                AllowedFilter::exact('order_id'),
            )
            ->allowedSorts('created_at', 'due_at', 'started_at')
            ->defaultSort('-created_at', '-id')
            ->withCount('items')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return WorkOrderResource::collection($workOrders);
    }

    /**
     * Kanban — ochiq ishlar, muhimlik va muddat bo'yicha tartiblangan
     * (§10: shoshilinch ish tepada, kechikkan qizil).
     */
    public function board(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', WorkOrder::class);

        $workOrders = WorkOrder::query()
            ->open()
            ->when(
                $request->integer('master_id') !== 0,
                fn ($query) => $query->where('master_id', $request->integer('master_id')),
            )
            ->byUrgency()
            ->with('items')
            ->paginate($this->perPage($request, 50))
            ->withQueryString();

        return WorkOrderResource::collection($workOrders);
    }

    public function show(WorkOrder $workOrder): WorkOrderResource
    {
        $this->authorize('view', $workOrder);

        return new WorkOrderResource($workOrder->load('items.variant'));
    }

    public function assign(
        AssignMasterRequest $request,
        WorkOrder $workOrder,
        ManageWorkOrder $manage,
    ): WorkOrderResource {
        $this->authorize('assign', $workOrder);

        return new WorkOrderResource($manage->assign($workOrder, $request->integer('master_id')));
    }

    public function start(Request $request, WorkOrder $workOrder, ManageWorkOrder $manage): WorkOrderResource
    {
        $this->authorize('start', $workOrder);

        return new WorkOrderResource($manage->start($this->currentUser($request), $workOrder));
    }

    public function finish(WorkOrder $workOrder, ManageWorkOrder $manage): WorkOrderResource
    {
        $this->authorize('finish', $workOrder);

        return new WorkOrderResource($manage->finish($workOrder));
    }

    public function setPriority(
        SetPriorityRequest $request,
        WorkOrder $workOrder,
        ManageWorkOrder $manage,
    ): WorkOrderResource {
        $this->authorize('setPriority', $workOrder);

        return new WorkOrderResource($manage->setPriority(
            $workOrder,
            WorkOrderPriority::from($request->string('priority')->toString()),
        ));
    }

    public function cancel(WorkOrder $workOrder, ManageWorkOrder $manage): WorkOrderResource
    {
        $this->authorize('cancel', $workOrder);

        return new WorkOrderResource($manage->cancel($workOrder));
    }

    /**
     * Materialni sarflash — `consume` (ANALIZ 3.4). Buyurtmaning mos
     * satri belgilanadi, shunda topshirishda tovar ikkinchi marta
     * chiqarilmaydi.
     */
    public function consume(
        ConsumeMaterialRequest $request,
        WorkOrder $workOrder,
        ConsumeMaterial $consume,
    ): WorkOrderResource {
        $this->authorize('consume', $workOrder);

        $item = $workOrder->items()->findOrFail($request->integer('item_id'));
        $locationId = $request->integer('location_id');

        $consume->handle(
            $this->currentUser($request),
            $item,
            $locationId === 0 ? null : Location::withoutGlobalScopes()->findOrFail($locationId),
        );

        return new WorkOrderResource($workOrder->fresh()?->load('items'));
    }

    /**
     * Brak — usta faqat sababni tanlaydi, qolganini tizim qiladi (7.5).
     */
    public function defect(
        MarkDefectRequest $request,
        WorkOrder $workOrder,
        MarkDefect $markDefect,
    ): JsonResponse {
        $this->authorize('defect', $workOrder);

        /** @var WorkOrderItem $item */
        $item = $workOrder->items()->findOrFail($request->integer('item_id'));

        $defect = $markDefect->handle(
            $this->currentUser($request),
            $workOrder,
            $item,
            DefectReason::from($request->string('reason')->toString()),
            $request->string('note')->toString(),
            $request->integer('quantity') ?: 1,
            $request->string('photo_path')->toString() ?: null,
        );

        return ApiResponse::created(
            (new DefectResource($defect))->resolve($request),
            ['work_order' => (new WorkOrderResource($workOrder->fresh()))->resolve($request)],
        );
    }
}
