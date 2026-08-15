<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Http\Controllers;

use App\Modules\Clinic\Actions\Prescription\TransferTicket;
use App\Modules\Clinic\Actions\Prescription\UpdatePrescription;
use App\Modules\Clinic\Actions\Prescription\WritePrescription;
use App\Modules\Clinic\Http\Requests\Prescription\PrescriptionRequest;
use App\Modules\Clinic\Http\Requests\Prescription\TransferTicketRequest;
use App\Modules\Clinic\Http\Requests\Prescription\UpdatePrescriptionRequest;
use App\Modules\Clinic\Http\Resources\PrescriptionResource;
use App\Modules\Clinic\Models\Prescription;
use App\Modules\Clinic\Models\Visit;
use App\Modules\Sales\Models\Customer;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Retseptlar va tiketlar — PROJECT.md 7.11.
 *
 * Ikkita boshqa-boshqa ro'yxat bor va ularni aralashtirmaslik shart:
 *
 * - `GET /prescriptions` — **faol tiketlar**, faqat o'z filialida.
 *   Bu sotuvchining ish ro'yxati.
 * - `GET /customers/{customer}/prescriptions` — **tarix**, barcha
 *   filiallar. Mijoz boshqa filialga kelganda sotuvchi eski retseptni
 *   ko'radi, lekin tiket avtomatik ochilmaydi — u ataylab "shu retsept
 *   bo'yicha buyurtma" qiladi.
 */
final class PrescriptionController extends ApiController
{
    /**
     * Faol tiketlar — sotuvchi ekranining ro'yxati (7.11).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Prescription::class);

        $actor = $this->currentUser($request);

        // Filial cheklovi so'rovga **oldindan** qo'shiladi: ustun
        // `branch_id` emas, `ticket_branch_id`, shuning uchun uni
        // global scope ham, `allowedFilters` ham bermaydi.
        $tickets = Prescription::query();

        if ($actor->canAccessAllBranches()) {
            $tickets->activeTickets();
        } else {
            $tickets->activeTicketsFor($actor->accessibleBranchIds());
        }

        $prescriptions = QueryBuilder::for($tickets)
            ->allowedFilters(
                AllowedFilter::exact('customer_id'),
                AllowedFilter::exact('doctor_id'),
            )
            ->allowedSorts('created_at')
            ->defaultSort('-created_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return PrescriptionResource::collection($prescriptions);
    }

    /**
     * Mijozning retsept tarixi — **barcha filiallar** (7.11).
     *
     * Bu ataylab alohida ruxsat ostida (`view_history`): tarix o'qish
     * uchun, ish esa faqat tiket bo'yicha boshlanadi.
     */
    public function history(Request $request, Customer $customer): AnonymousResourceCollection
    {
        $this->authorize('viewHistory', Prescription::class);

        $prescriptions = Prescription::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return PrescriptionResource::collection($prescriptions);
    }

    public function show(Prescription $prescription): PrescriptionResource
    {
        $this->authorize('view', $prescription);

        return new PrescriptionResource($prescription);
    }

    /**
     * Retsept yozish — saqlangan zahoti sotuvchida tiket ochiladi.
     *
     * Javobda `warnings` bo'lishi mumkin: diopter oldingi retseptdan
     * keskin farq qilsa, shifokor qayta tekshirib ko'rishi kerak
     * (§10). Bu **taqiq emas** — retsept saqlanadi.
     */
    public function store(
        PrescriptionRequest $request,
        WritePrescription $write,
    ): JsonResponse {
        $this->authorize('create', Prescription::class);

        $doctor = $this->currentUser($request);
        $customerId = $request->integer('customer_id');
        $branchId = $request->integer('branch_id');

        if (! $doctor->canAccessAllBranches() && ! in_array($branchId, $doctor->accessibleBranchIds(), true)) {
            throw ValidationException::withMessages([
                'branch_id' => __('core::shift.foreign_branch'),
            ]);
        }

        $previous = $write->previousFor($customerId);

        $prescription = $write->handle(
            $doctor,
            $customerId,
            $branchId,
            $request->values(),
            $this->visitFrom($request),
        );

        return ApiResponse::created(
            (new PrescriptionResource($prescription))->resolve($request),
            $this->warnings($prescription, $previous),
        );
    }

    public function update(
        UpdatePrescriptionRequest $request,
        Prescription $prescription,
        UpdatePrescription $update,
    ): PrescriptionResource {
        $this->authorize('update', $prescription);

        $updated = $update->handle($this->currentUser($request), $prescription, $request->values());

        return new PrescriptionResource($updated);
    }

    /**
     * Tiketni boshqa filialga o'tkazish — qoidadan chekinish (7.11),
     * sabab majburiy va logga yoziladi.
     */
    public function transferTicket(
        TransferTicketRequest $request,
        Prescription $prescription,
        TransferTicket $transfer,
    ): PrescriptionResource {
        $this->authorize('transferTicket', $prescription);

        $moved = $transfer->handle(
            $this->currentUser($request),
            $prescription,
            $request->integer('to_branch_id'),
            $request->string('reason')->toString(),
        );

        return new PrescriptionResource($moved);
    }

    private function visitFrom(PrescriptionRequest $request): ?Visit
    {
        $visitId = $request->integer('visit_id');

        return $visitId === 0 ? null : Visit::withoutGlobalScopes()->findOrFail($visitId);
    }

    /**
     * @return array<string, mixed>
     */
    private function warnings(Prescription $prescription, ?Prescription $previous): array
    {
        if (! $prescription->hasSignificantJumpFrom($previous)) {
            return [];
        }

        return [
            'warnings' => [
                __('clinic::prescription.diopter_jump', [
                    'threshold' => (string) config('optika.prescriptions.diopter_change_warning', '1.50'),
                ]),
            ],
        ];
    }
}
