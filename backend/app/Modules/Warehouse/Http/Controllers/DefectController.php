<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Controllers;

use App\Modules\Warehouse\Http\Resources\DefectResource;
use App\Modules\Warehouse\Models\Defect;
use App\Support\Http\ApiController;
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
 * Brak yozuvi ustaxonadan (`POST /work-orders/{id}/defect`) yoki
 * qaytarishdan avtomatik tug'iladi — shuning uchun bu yerda `store`
 * yo'q: "usta faqat sababni tanlaydi, qolganini tizim qiladi".
 */
final class DefectController extends ApiController
{
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
