<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Http\Controllers;

use App\Modules\Clinic\Actions\Visit\ChangeVisitStatus;
use App\Modules\Clinic\Actions\Visit\CreateVisit;
use App\Modules\Clinic\Enums\VisitSource;
use App\Modules\Clinic\Http\Requests\Visit\StoreVisitRequest;
use App\Modules\Clinic\Http\Resources\VisitResource;
use App\Modules\Clinic\Models\Visit;
use App\Modules\Core\Models\Branch;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Vizitlar va navbat — PROJECT.md §6.5.
 *
 *     waiting → in_progress → finished
 *             → no_show | cancelled
 *
 * Ro'yxat `BranchScope` bilan cheklanadi: boshqa filial navbatini
 * ko'rishning ma'nosi yo'q.
 */
final class VisitController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Visit::class);

        $visits = QueryBuilder::for(Visit::class)
            ->allowedFilters(
                AllowedFilter::exact('branch_id'),
                AllowedFilter::exact('customer_id'),
                AllowedFilter::exact('doctor_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('source'),
                AllowedFilter::exact('queue_date'),
            )
            ->allowedSorts('queue_number', 'created_at', 'started_at')
            ->defaultSort('queue_number')
            ->with('customer')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return VisitResource::collection($visits);
    }

    /**
     * Shifokor ekranidagi **jonli navbat** (§6.5): bugungi kutayotgan
     * va ketayotgan vizitlar, raqam tartibida.
     */
    public function queue(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Visit::class);

        $visits = Visit::query()
            ->open()
            ->forDate($request->string('date')->toString() ?: now()->toDateString())
            ->with('customer')
            ->orderBy('queue_number')
            ->paginate($this->perPage($request, 50))
            ->withQueryString();

        return VisitResource::collection($visits);
    }

    public function show(Visit $visit): VisitResource
    {
        $this->authorize('view', $visit);

        return new VisitResource($visit->load(['customer', 'prescription']));
    }

    public function store(StoreVisitRequest $request, CreateVisit $create): JsonResponse
    {
        $this->authorize('create', Visit::class);

        $actor = $this->currentUser($request);
        $branch = Branch::findOrFail($request->integer('branch_id'));

        if (! $actor->canAccessAllBranches() && ! in_array($branch->id, $actor->accessibleBranchIds(), true)) {
            throw ValidationException::withMessages([
                'branch_id' => __('core::shift.foreign_branch'),
            ]);
        }

        $visit = $create->handle(
            $actor,
            $branch,
            $request->integer('customer_id'),
            VisitSource::from($request->string('source')->toString() ?: 'walk_in'),
            $request->integer('doctor_id') ?: null,
        );

        return ApiResponse::created(
            (new VisitResource($visit->load('customer')))->resolve($request),
        );
    }

    public function start(Request $request, Visit $visit, ChangeVisitStatus $change): VisitResource
    {
        $this->authorize('start', $visit);

        return new VisitResource($change->start($this->currentUser($request), $visit));
    }

    public function finish(Visit $visit, ChangeVisitStatus $change): VisitResource
    {
        $this->authorize('finish', $visit);

        return new VisitResource($change->finish($visit));
    }

    public function cancel(Visit $visit, ChangeVisitStatus $change): VisitResource
    {
        $this->authorize('cancel', $visit);

        return new VisitResource($change->cancel($visit));
    }

    /**
     * Mijoz kelmadi — `cancelled` dan ataylab ajratilgan (ENUMS.md §5).
     */
    public function noShow(Visit $visit, ChangeVisitStatus $change): VisitResource
    {
        $this->authorize('cancel', $visit);

        return new VisitResource($change->markNoShow($visit));
    }
}
