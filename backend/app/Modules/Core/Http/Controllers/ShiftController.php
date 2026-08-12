<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Core\Actions\Shift\CloseShift;
use App\Modules\Core\Actions\Shift\OpenShift;
use App\Modules\Core\Http\Requests\Shift\CloseShiftRequest;
use App\Modules\Core\Http\Requests\Shift\OpenShiftRequest;
use App\Modules\Core\Http\Resources\ShiftResource;
use App\Modules\Core\Models\Shift;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Smenalar — PROJECT.md 5.2, §15 #24.
 *
 * Ro'yxat `BranchScope` bilan avtomatik cheklanadi (`Shift` modeli
 * `BelongsToBranch` ni ishlatadi) — sotuvchi begona filial smenasini
 * ko'rmaydi.
 */
final class ShiftController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Shift::class);

        $shifts = QueryBuilder::for(Shift::class)
            ->allowedFilters(
                AllowedFilter::exact('branch_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('opened_by'),
            )
            ->allowedSorts('opened_at', 'closed_at')
            ->defaultSort('-opened_at')
            ->with(['branch', 'openedBy', 'closedBy'])
            ->paginate($this->perPage($request))
            ->withQueryString();

        return ShiftResource::collection($shifts);
    }

    public function show(Shift $shift): ShiftResource
    {
        $this->authorize('view', $shift);

        return new ShiftResource($shift->load(['branch', 'openedBy', 'closedBy']));
    }

    /**
     * Joriy ochiq smena — kassa ekrani har kirganda shuni so'raydi (§10).
     */
    public function current(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Shift::class);

        $shift = Shift::query()
            ->active()
            ->with(['branch', 'openedBy'])
            ->latest('opened_at')
            ->first();

        return ApiResponse::data(
            $shift instanceof Shift ? (new ShiftResource($shift))->resolve($request) : null,
        );
    }

    public function open(OpenShiftRequest $request, OpenShift $openShift): JsonResponse
    {
        $this->authorize('open', Shift::class);

        $actor = $this->currentUser($request);
        $branchId = $request->integer('branch_id');

        if (! $actor->canAccessAllBranches() && ! in_array($branchId, $actor->accessibleBranchIds(), true)) {
            throw ValidationException::withMessages([
                'branch_id' => __('core::shift.foreign_branch'),
            ]);
        }

        $shift = $openShift->handle(
            $actor,
            $branchId,
            Money::of($request->string('opening_cash')->toString()),
        );

        return ApiResponse::created(
            (new ShiftResource($shift->load(['branch', 'openedBy'])))->resolve($request),
        );
    }

    public function close(CloseShiftRequest $request, Shift $shift, CloseShift $closeShift): ShiftResource
    {
        $this->authorize('close', $shift);

        $closed = $closeShift->handle(
            $this->currentUser($request),
            $shift,
            Money::of($request->string('actual_cash')->toString()),
            $request->string('note')->toString() ?: null,
        );

        return new ShiftResource($closed->load(['branch', 'openedBy', 'closedBy']));
    }
}
