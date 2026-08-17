<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Finance\Actions\Debt\RecordDebtReminder;
use App\Modules\Finance\Actions\Debt\WriteOffDebt;
use App\Modules\Finance\Enums\ReminderChannel;
use App\Modules\Finance\Enums\ReminderResponse;
use App\Modules\Finance\Http\Requests\Debt\RemindDebtRequest;
use App\Modules\Finance\Http\Requests\Debt\WriteOffDebtRequest;
use App\Modules\Finance\Http\Resources\DebtReminderResource;
use App\Modules\Finance\Http\Resources\DebtResource;
use App\Modules\Finance\Models\Debt;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Qarz registri — PROJECT.md 7.6, BOSQICH-10.md §10a.
 */
final class DebtController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Debt::class);

        $debts = QueryBuilder::for(Debt::class)
            ->allowedFilters(
                AllowedFilter::exact('branch_id'),
                AllowedFilter::exact('customer_id'),
                AllowedFilter::exact('status'),
            )
            ->allowedSorts('due_date', 'amount', 'created_at')
            ->defaultSort('due_date')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return DebtResource::collection($debts);
    }

    public function show(Request $request, Debt $debt): DebtResource
    {
        $this->authorize('view', $debt);

        return new DebtResource($debt->load('reminders'));
    }

    public function writeOff(WriteOffDebtRequest $request, Debt $debt, WriteOffDebt $action): JsonResponse
    {
        $this->authorize('writeOff', $debt);

        $debt = $action->handle($this->currentUser($request), $debt, $request->string('reason')->toString());

        return ApiResponse::data((new DebtResource($debt))->resolve($request));
    }

    public function remind(RemindDebtRequest $request, Debt $debt, RecordDebtReminder $action): JsonResponse
    {
        $this->authorize('remind', $debt);

        $reminder = $action->handle(
            $this->currentUser($request),
            $debt,
            ReminderChannel::from($request->string('channel')->toString()),
            $request->filled('response')
                ? ReminderResponse::from($request->string('response')->toString())
                : ReminderResponse::None,
            $request->string('note')->toString() ?: null,
        );

        return ApiResponse::created((new DebtReminderResource($reminder))->resolve($request));
    }
}
