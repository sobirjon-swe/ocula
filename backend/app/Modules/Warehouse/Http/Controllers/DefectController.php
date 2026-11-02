<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Controllers;

use App\Modules\Core\Models\Location;
use App\Modules\Warehouse\Actions\Defect\ReportDefect;
use App\Modules\Warehouse\Enums\DefectReason;
use App\Modules\Warehouse\Http\Requests\StoreDefectRequest;
use App\Modules\Warehouse\Http\Resources\DefectResource;
use App\Modules\Warehouse\Models\Defect;
use App\Modules\Warehouse\Models\Transfer;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Brak — PROJECT.md 7.5, SCHEMA.md §3.
 *
 * Yozuv **tahrirlanmaydi va o'chirilmaydi**: u ombor harakatini
 * tug'dirgan bo'lishi mumkin. Xato bo'lsa harakat storno qilinadi
 * (7.21).
 *
 * Brak ustaxona/qaytarish jarayonidan yoki vakolatli ombor xodimining
 * alohida hisobdan chiqarish formasidan yaratiladi.
 */
final class DefectController extends ApiController
{
    public function store(StoreDefectRequest $request, ReportDefect $action): JsonResponse
    {
        $actor = $this->currentUser($request);
        abort_unless($actor->can('warehouse.defect.report'), 403);
        $location = Location::findOrFail($request->integer('location_id'));
        $links = [];
        if ($request->filled('transfer_id')) {
            $transfer = Transfer::findOrFail($request->integer('transfer_id'));
            $links['transfer_id'] = $transfer->id;
        }
        $defect = $action->handle($actor, $location, $request->integer('variant_id'), $request->integer('quantity'),
            DefectReason::from($request->string('reason')->toString()),
            $request->string('note')->toString() ?: $request->string('reason')->toString(), $links);

        return ApiResponse::created((new DefectResource($defect))->resolve($request));
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Defect::class);

        $defects = QueryBuilder::for(Defect::class)
            ->allowedFilters(
                AllowedFilter::exact('branch_id'),
                AllowedFilter::exact('reason'),
                AllowedFilter::exact('variant_id'),
                AllowedFilter::exact('order_id'),
                AllowedFilter::exact('work_order_id'),
            )
            ->allowedSorts('created_at', 'quantity')
            ->defaultSort('-created_at', '-id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return DefectResource::collection($defects);
    }

    public function show(Defect $defect): DefectResource
    {
        $this->authorize('view', $defect);

        return new DefectResource($defect);
    }

    /**
     * Direktor brakni ko'rib chiqdi — kim to'lashi tasdiqlandi (7.5).
     */
    public function approve(Request $request, Defect $defect): DefectResource
    {
        $this->authorize('approve', $defect);

        $defect->update(['approved_by' => $this->currentUser($request)->id]);

        return new DefectResource($defect);
    }
}
