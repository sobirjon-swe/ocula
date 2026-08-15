<?php

declare(strict_types=1);

namespace App\Modules\Workshop\Actions\WorkOrder;

use App\Modules\Core\Models\User;
use App\Modules\Workshop\Enums\WorkOrderPriority;
use App\Modules\Workshop\Enums\WorkOrderStatus;
use App\Modules\Workshop\Models\WorkOrder;
use Illuminate\Validation\ValidationException;

/**
 * Kanbandagi harakatlar — PROJECT.md §6.6, §10 (usta ekrani).
 *
 *     queued → in_progress → done
 *
 * Ishni **boshlagan usta** buyruqqa yoziladi: brak statistikasi va
 * mukofot (7.12) aynan shu bog'lanishga tayanadi. Umumiy planshetda
 * PIN bilan almashish ham shuning uchun (7.14) — aks holda hamma
 * ish bitta akkauntga yozilib, statistika yolg'on bo'lardi.
 */
final class ManageWorkOrder
{
    public function assign(WorkOrder $workOrder, int $masterId): WorkOrder
    {
        if ($workOrder->status->isOpen() === false) {
            throw ValidationException::withMessages([
                'status' => __('workshop::work_order.not_open', ['status' => $workOrder->status->value]),
            ]);
        }

        $workOrder->update(['master_id' => $masterId]);

        return $workOrder;
    }

    public function start(User $master, WorkOrder $workOrder): WorkOrder
    {
        if (! $workOrder->status->canBeStarted()) {
            throw ValidationException::withMessages([
                'status' => __('workshop::work_order.not_startable', ['status' => $workOrder->status->value]),
            ]);
        }

        $workOrder->update([
            'status' => WorkOrderStatus::InProgress,
            // Biriktirilmagan bo'lsa — ishni olgan usta yoziladi.
            'master_id' => $workOrder->master_id ?? $master->id,
            'started_at' => now(),
        ]);

        return $workOrder;
    }

    public function finish(WorkOrder $workOrder): WorkOrder
    {
        if (! $workOrder->status->canBeFinished()) {
            throw ValidationException::withMessages([
                'status' => __('workshop::work_order.not_finishable', ['status' => $workOrder->status->value]),
            ]);
        }

        $workOrder->update([
            'status' => WorkOrderStatus::Done,
            'finished_at' => now(),
        ]);

        return $workOrder;
    }

    public function setPriority(WorkOrder $workOrder, WorkOrderPriority $priority): WorkOrder
    {
        $workOrder->update(['priority' => $priority]);

        return $workOrder;
    }

    public function cancel(WorkOrder $workOrder): WorkOrder
    {
        if (! $workOrder->status->canBeCancelled()) {
            throw ValidationException::withMessages([
                'status' => __('workshop::work_order.not_cancellable', ['status' => $workOrder->status->value]),
            ]);
        }

        $workOrder->update(['status' => WorkOrderStatus::Cancelled]);

        return $workOrder;
    }
}
