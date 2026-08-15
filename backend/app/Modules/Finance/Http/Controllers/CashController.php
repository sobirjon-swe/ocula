<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Core\Models\Shift;
use App\Modules\Finance\Enums\CashCategory;
use App\Modules\Finance\Http\Requests\Cash\ReverseCashMovementRequest;
use App\Modules\Finance\Http\Requests\Cash\StoreCashMovementRequest;
use App\Modules\Finance\Http\Resources\CashMovementResource;
use App\Modules\Finance\Models\CashMovement;
use App\Modules\Finance\Services\CashRegister;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use App\Support\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Kassa daftari — PROJECT.md 7.8-A, 7.21.
 *
 * Ro'yxat `BranchScope` bilan cheklanadi: sotuvchi o'z filiali kassasini
 * ko'radi, direktor hammasini.
 *
 * Yozuvlar tahrirlanmaydi va o'chirilmaydi — shu sababli bu yerda
 * `update`/`destroy` yo'q, faqat `reverse`.
 */
final class CashController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CashMovement::class);

        $movements = QueryBuilder::for(CashMovement::class)
            ->allowedFilters(
                AllowedFilter::exact('branch_id'),
                AllowedFilter::exact('shift_id'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('category'),
            )
            ->allowedSorts('created_at', 'amount')
            ->defaultSort('-created_at', '-id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return CashMovementResource::collection($movements);
    }

    /**
     * Joriy smena bo'yicha kassa holati — kassir ekrani shuni so'raydi.
     *
     * Smena ochilmagan bo'lsa `null` qaytadi: bu xato emas, shunchaki
     * hali ish boshlanmagan.
     */
    public function summary(Request $request, CashRegister $register): JsonResponse
    {
        $this->authorize('viewAny', CashMovement::class);

        $shift = Shift::query()->active()->latest('opened_at')->first();

        if (! $shift instanceof Shift) {
            return ApiResponse::data(null);
        }

        $expected = $register->expectedCash($shift);

        return ApiResponse::data([
            'shift_id' => $shift->id,
            'branch_id' => $shift->branch_id,
            'opening_cash' => $shift->opening_cash->toString(),
            'expected_cash' => $expected->toString(),
            'expected_cash_formatted' => $expected->format(),
            'by_category' => $this->byCategory($shift),
        ]);
    }

    public function store(StoreCashMovementRequest $request, CashRegister $register): JsonResponse
    {
        $this->authorize('create', CashMovement::class);

        $actor = $this->currentUser($request);
        $branchId = $request->integer('branch_id');

        if (! $actor->canAccessAllBranches() && ! in_array($branchId, $actor->accessibleBranchIds(), true)) {
            throw ValidationException::withMessages([
                'branch_id' => __('core::shift.foreign_branch'),
            ]);
        }

        $movement = $register->record(
            $actor,
            $branchId,
            CashCategory::from($request->string('category')->toString()),
            Money::of($request->string('amount')->toString()),
            description: $request->string('description')->toString() ?: null,
        );

        return ApiResponse::created(
            (new CashMovementResource($movement))->resolve($request),
        );
    }

    public function reverse(
        ReverseCashMovementRequest $request,
        CashMovement $movement,
        CashRegister $register,
    ): JsonResponse {
        $this->authorize('reverse', $movement);

        $reversal = $register->reverse(
            $this->currentUser($request),
            $movement,
            $request->string('reason')->toString(),
        );

        return ApiResponse::created(
            (new CashMovementResource($reversal))->resolve($request),
        );
    }

    /**
     * Toifalar kesimida ishorali yig'indi — kunlik kassa hisobotining
     * qatorlari (7.8-A).
     *
     * Bu yerda model emas, so'rov quruvchisi ishlatiladi: bizga
     * `CashMovement` obyektlari emas, ikkita ustunli jadval kerak.
     *
     * @return array<int, array<string, string>>
     */
    private function byCategory(Shift $shift): array
    {
        $rows = DB::table('cash_movements')
            ->where('shift_id', $shift->id)
            ->selectRaw("category, COALESCE(SUM(CASE WHEN type = 'in' THEN amount ELSE -amount END), 0) AS net")
            ->groupBy('category')
            ->orderBy('category')
            ->get();

        return $rows->map(static fn (object $row): array => [
            'category' => (string) $row->category,
            'net' => Money::of((string) $row->net)->toString(),
        ])->all();
    }
}
