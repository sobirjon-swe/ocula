<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Http\Requests\Service\ServiceRequest;
use App\Modules\Catalog\Http\Resources\ServiceResource;
use App\Modules\Catalog\Models\Service;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Xizmatlar — PERMISSIONS.md §2 (`catalog.service.manage`).
 *
 * Xizmat o'chirilmaydi, faqat `is_active = false` bo'ladi: o'tgan
 * buyurtmalarda unga havola qolgan.
 */
final class ServiceController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Service::class);

        $services = QueryBuilder::for(Service::class)
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('is_active'),
            )
            ->allowedSorts('name', 'price')
            ->defaultSort('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return ServiceResource::collection($services);
    }

    public function store(ServiceRequest $request): JsonResponse
    {
        $this->authorize('create', Service::class);

        $service = Service::create($this->normalized($request));

        return ApiResponse::created((new ServiceResource($service))->resolve($request));
    }

    public function show(Service $service): ServiceResource
    {
        $this->authorize('view', $service);

        return new ServiceResource($service);
    }

    public function update(ServiceRequest $request, Service $service): ServiceResource
    {
        $this->authorize('update', $service);

        $service->update($this->normalized($request));

        return new ServiceResource($service);
    }

    public function destroy(Service $service): JsonResponse
    {
        $this->authorize('delete', $service);

        $service->update(['is_active' => false]);

        return ApiResponse::noContent();
    }

    /**
     * Narx `Money` orqali o'tkaziladi: float hech qachon bazaga tushmasin
     * (PROJECT.md §13 dagi taqiq).
     *
     * @return array<string, mixed>
     */
    private function normalized(ServiceRequest $request): array
    {
        $data = $request->validated();

        if (array_key_exists('price', $data)) {
            $data['price'] = Money::of((string) $data['price'])->toString();
        }

        return $data;
    }
}
