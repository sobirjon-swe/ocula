<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Actions\StockRequest;

use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Enums\StockRequestStatus;
use App\Modules\Warehouse\Models\StockRequest;
use Illuminate\Validation\ValidationException;

/**
 * So'rovni tasdiqlash yoki rad etish — ENUMS.md §3.
 *
 * Qaror **so'ralayotgan** filialniki: tovar ularning omborida turibdi
 * va ular o'z mijozlari uchun ham kerakligini biladi. Shuning uchun
 * so'ragan filial o'z so'rovini o'zi tasdiqlay olmaydi.
 */
final class DecideStockRequest
{
    public function approve(User $approver, StockRequest $request): StockRequest
    {
        return $this->decide($approver, $request, StockRequestStatus::Approved);
    }

    public function reject(User $approver, StockRequest $request): StockRequest
    {
        return $this->decide($approver, $request, StockRequestStatus::Rejected);
    }

    /**
     * So'ragan filial o'z so'rovini bekor qiladi (mijoz kutmadi,
     * tovar boshqa yerdan topildi).
     */
    public function cancel(StockRequest $request): StockRequest
    {
        if (! $request->status->canBeCancelled()) {
            throw ValidationException::withMessages([
                'status' => __('warehouse::request.not_cancellable'),
            ]);
        }

        $request->update(['status' => StockRequestStatus::Cancelled]);

        return $request;
    }

    private function decide(User $approver, StockRequest $request, StockRequestStatus $target): StockRequest
    {
        if (! $request->status->canBeDecided()) {
            throw ValidationException::withMessages([
                'status' => __('warehouse::request.already_decided'),
            ]);
        }

        if (! $this->belongsToSupplyingBranch($approver, $request)) {
            throw ValidationException::withMessages([
                'status' => __('warehouse::request.only_supplying_branch'),
            ]);
        }

        $request->update([
            'status' => $target,
            'approved_by' => $approver->id,
        ]);

        return $request;
    }

    /**
     * Qaror qabul qiluvchi tovar chiqadigan filialdanmi.
     *
     * Direktor har ikki tomonni ko'radi, shuning uchun unga cheklov
     * qo'yilmaydi — u aralashishi kerak bo'lgan holatlar bo'ladi.
     */
    private function belongsToSupplyingBranch(User $approver, StockRequest $request): bool
    {
        if ($approver->canAccessAllBranches()) {
            return true;
        }

        return in_array($request->to_branch_id, $approver->accessibleBranchIds(), true);
    }
}
