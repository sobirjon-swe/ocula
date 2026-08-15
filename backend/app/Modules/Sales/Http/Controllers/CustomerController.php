<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Sales\Http\Requests\Customer\CustomerRequest;
use App\Modules\Sales\Http\Resources\CustomerResource;
use App\Modules\Sales\Models\Customer;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Mijozlar — SCHEMA.md §4.
 *
 * Ro'yxat **filial bo'yicha cheklanmaydi**: mijoz butun tarmoqniki.
 * Boshqa filialda ro'yxatdan o'tgan odam kelganda ham o'sha kartochka
 * topilishi kerak, aks holda dublikat ochilib, qarz va retsept tarixi
 * ikkiga bo'linib ketardi.
 *
 * Mijoz o'chirilmaydi — tarixi bilan qoladi.
 */
final class CustomerController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);

        $customers = QueryBuilder::for(Customer::class)
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::partial('phone'),
                AllowedFilter::exact('telegram_id'),
                AllowedFilter::exact('branch_id'),
            )
            ->allowedSorts('name', 'created_at')
            ->defaultSort('name')
            ->withCount('orders')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return CustomerResource::collection($customers);
    }

    public function show(Customer $customer): CustomerResource
    {
        $this->authorize('view', $customer);

        return new CustomerResource($customer->loadCount('orders'));
    }

    public function store(CustomerRequest $request): JsonResponse
    {
        $this->authorize('create', Customer::class);

        $actor = $this->currentUser($request);

        $customer = Customer::create([
            ...$request->validated(),
            // Birinchi kelgan filial — keyin o'zgarmaydi (SCHEMA.md §4).
            'branch_id' => $request->integer('branch_id') ?: $actor->branch_id,
            'first_visit_at' => now(),
            'created_by' => $actor->id,
        ]);

        return ApiResponse::created((new CustomerResource($customer))->resolve($request));
    }

    public function update(CustomerRequest $request, Customer $customer): CustomerResource
    {
        $this->authorize('update', $customer);

        $customer->update($request->validated());

        return new CustomerResource($customer);
    }
}
