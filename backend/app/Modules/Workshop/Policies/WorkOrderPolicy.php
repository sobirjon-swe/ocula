<?php

declare(strict_types=1);

namespace App\Modules\Workshop\Policies;

use App\Modules\Core\Models\User;
use App\Support\Authorization\ResourcePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Ish buyrug'i — PERMISSIONS.md §6.
 *
 * `create` **yo'q**: buyruq buyurtma ustaxonaga o'tganda tizim
 * tomonidan tug'iladi (§6.6), shuning uchun uni qo'lda yaratish
 * imkoni ham bo'lmasligi kerak.
 *
 * Materialni sarflash omborga tegadi, shuning uchun u alohida
 * ruxsat ostida (`warehouse.master_stock.consume`) — usta buyruqni
 * yuritishi bilan tovarni hisobdan chiqarishi bir xil mas'uliyat emas.
 */
final class WorkOrderPolicy extends ResourcePolicy
{
    protected function permission(): string
    {
        return 'workshop.work_order';
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('workshop.work_order.view_any') && $this->withinBranch($user, $model);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function assign(User $user, Model $model): bool
    {
        return $user->can('workshop.work_order.assign') && $this->withinBranch($user, $model);
    }

    public function start(User $user, Model $model): bool
    {
        return $user->can('workshop.work_order.start') && $this->withinBranch($user, $model);
    }

    public function finish(User $user, Model $model): bool
    {
        return $user->can('workshop.work_order.finish') && $this->withinBranch($user, $model);
    }

    public function defect(User $user, Model $model): bool
    {
        return $user->can('workshop.work_order.defect') && $this->withinBranch($user, $model);
    }

    public function setPriority(User $user, Model $model): bool
    {
        return $user->can('workshop.work_order.set_priority') && $this->withinBranch($user, $model);
    }

    /**
     * Materialni sarflash — ombor amali (ANALIZ 3.4).
     */
    public function consume(User $user, Model $model): bool
    {
        return $user->can('warehouse.master_stock.consume') && $this->withinBranch($user, $model);
    }

    /**
     * Buyruq bekor qilinadi, o'chirilmaydi: u bo'yicha material
     * sarflangan bo'lishi mumkin.
     */
    public function cancel(User $user, Model $model): bool
    {
        return $user->can('workshop.work_order.assign') && $this->withinBranch($user, $model);
    }

    public function update(User $user, Model $model): bool
    {
        return false;
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
