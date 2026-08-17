<?php

declare(strict_types=1);

namespace App\Modules\Customer\Http\Controllers;

use App\Modules\Clinic\Models\Prescription;
use App\Modules\Customer\Http\Resources\CustomerPrescriptionResource;
use App\Support\Http\CustomerApiController;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mijozning o'z retseptlari — PROJECT.md §6.9, 7.11, PERMISSIONS.md §12.
 *
 * Retsept tarixi **barcha filiallardan** — 7.11 jadvalidagi "Retsept
 * tarixi — barcha filiallar (o'qish uchun)" qatori mijozning o'ziga
 * ham tegishli, filial cheklovi faqat faol tiketga tegishli edi.
 */
final class PrescriptionController extends CustomerApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $customer = $this->currentCustomer($request);

        if (! $customer->can('customer.prescription.view_own')) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $prescriptions = Prescription::query()
            ->where('customer_id', $customer->id)
            ->with(['branch', 'doctor'])
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return CustomerPrescriptionResource::collection($prescriptions);
    }
}
