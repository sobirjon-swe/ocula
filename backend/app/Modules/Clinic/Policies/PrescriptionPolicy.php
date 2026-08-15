<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Policies;

use App\Modules\Clinic\Models\Prescription;
use App\Modules\Core\Models\User;
use App\Support\Authorization\ResourcePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Retsept va tiket — PERMISSIONS.md §5, PROJECT.md 7.11.
 *
 * Bu yerda **ikkita boshqa-boshqa ruxsat** bor va ularni aralashtirmaslik
 * bosqichning eng muhim qoidasi:
 *
 * - `clinic.prescription.view` — **faol tiket**, faqat
 *   `ticket_branch_id` filialida. Bu ish hujjati: uni ko'rgan odam
 *   ko'zoynak yasashni boshlaydi.
 * - `clinic.prescription.view_history` — **tarix**, barcha filiallarda,
 *   faqat o'qish uchun. Mijoz boshqa filialga kelganda sotuvchi eski
 *   retseptni ko'rishi kerak, lekin ish o'sha yerda boshlanib
 *   ketmasligi kerak.
 *
 * Yozish faqat shifokorda (`director` dan ham ataylab olib tashlangan —
 * tibbiy javobgarlik).
 */
final class PrescriptionPolicy extends ResourcePolicy
{
    protected function permission(): string
    {
        return 'clinic.prescription';
    }

    /**
     * Faol tiketlar ro'yxati — filial cheklovi kontrollerda so'rovga
     * qo'shiladi (`activeTicketsFor`), chunki cheklov ustuni
     * `branch_id` emas, `ticket_branch_id`.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('clinic.prescription.view');
    }

    /**
     * Kartochkani ochish: faol tiket o'z filialida bo'lsa, yoki
     * odamda tarixni o'qish huquqi bo'lsa.
     */
    public function view(User $user, Model $model): bool
    {
        if ($user->can('clinic.prescription.view_history')) {
            return true;
        }

        return $user->can('clinic.prescription.view') && $this->ticketIsHere($user, $model);
    }

    /**
     * Mijozning retsept tarixi — barcha filiallar (7.11).
     */
    public function viewHistory(User $user): bool
    {
        return $user->can('clinic.prescription.view_history');
    }

    public function create(User $user): bool
    {
        return $user->can('clinic.prescription.create');
    }

    /**
     * Tahrirlash — "o'zi yozgan va 24 soat ichida" sharti Action'da
     * (`UpdatePrescription`): u yozuvning yoshiga bog'liq va Policy
     * darajasida tekshirilsa, xato xabari "ruxsat yo'q" bo'lib
     * chiqardi — aslida sabab boshqa.
     */
    public function update(User $user, Model $model): bool
    {
        return $user->can('clinic.prescription.update');
    }

    /**
     * Tiketni boshqa filialga o'tkazish — qoidadan chekinish (7.11).
     */
    public function transferTicket(User $user, Model $model): bool
    {
        return $user->can('clinic.ticket.transfer_branch');
    }

    /**
     * Retsept o'chirilmaydi: u bo'yicha ko'zoynak yasalgan bo'lishi
     * mumkin va tibbiy hujjat sifatida qoladi.
     */
    public function delete(User $user, Model $model): bool
    {
        return false;
    }

    /**
     * Faol tiket shu foydalanuvchining filialidami (7.11).
     */
    private function ticketIsHere(User $user, Model $model): bool
    {
        if (! $model instanceof Prescription || $user->canAccessAllBranches()) {
            return true;
        }

        return $model->ticket_active
            && in_array($model->ticket_branch_id, $user->accessibleBranchIds(), true);
    }
}
