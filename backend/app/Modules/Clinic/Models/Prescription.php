<?php

declare(strict_types=1);

namespace App\Modules\Clinic\Models;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use App\Modules\Sales\Models\Customer;
use Carbon\CarbonImmutable;
use Database\Factories\PrescriptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Retsept va uning tiketi — SCHEMA.md §5, PROJECT.md 7.11.
 *
 * Tiket alohida jadval emas: u shu qatorning `ticket_active` +
 * `ticket_branch_id` juftligi. Retsept saqlanganda tiket ochiladi va
 * sotuvchi ekranida paydo bo'ladi.
 *
 * **`BelongsToBranch` ataylab ishlatilmaydi.** Bu yerda ikki xil
 * ko'rinish bor va ularni bitta global scope bilan ifodalab bo'lmaydi
 * (7.11):
 *
 * | Nima | Kim ko'radi | Scope |
 * |---|---|---|
 * | Faol tiket | faqat `ticket_branch_id` filiali | `activeTicketsFor()` |
 * | Retsept tarixi | barcha filiallar (o'qish) | cheklovsiz |
 *
 * Global scope qo'ysak, tarixni har safar chetlab o'tishga to'g'ri
 * kelardi va "chetlab o'tish" odatga aylanib, cheklovning ma'nosi
 * yo'qolardi.
 *
 * @property int $id
 * @property int|null $visit_id
 * @property int $customer_id
 * @property int $branch_id
 * @property int $doctor_id
 * @property string|null $od_sph
 * @property string|null $od_cyl
 * @property int|null $od_axis
 * @property string|null $od_add
 * @property string|null $os_sph
 * @property string|null $os_cyl
 * @property int|null $os_axis
 * @property string|null $os_add
 * @property string|null $pd
 * @property string|null $pd_near
 * @property string|null $prism
 * @property string|null $notes
 * @property CarbonImmutable $valid_until
 * @property bool $ticket_active
 * @property int $ticket_branch_id
 * @property CarbonImmutable|null $created_at
 */
class Prescription extends Model
{
    /** @use HasFactory<PrescriptionFactory> */
    use HasFactory;

    /** Tahrirlash oynasi — PERMISSIONS.md §5. */
    public const int EDIT_WINDOW_HOURS = 24;

    protected $fillable = [
        'visit_id', 'customer_id', 'branch_id', 'doctor_id',
        'od_sph', 'od_cyl', 'od_axis', 'od_add',
        'os_sph', 'os_cyl', 'os_axis', 'os_add',
        'pd', 'pd_near', 'prism', 'notes',
        'valid_until', 'ticket_active', 'ticket_branch_id',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'ticket_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'od_axis' => 'integer',
            'os_axis' => 'integer',
            'valid_until' => 'date',
            'ticket_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Retsept yozilgan filial.
     *
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Tiket qaysi filialda ochiq (7.11) — yozilgan filialdan farq
     * qilishi mumkin, agar tiket ko'chirilgan bo'lsa.
     *
     * @return BelongsTo<Branch, $this>
     */
    public function ticketBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'ticket_branch_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    /**
     * @return HasMany<PrescriptionTransfer, $this>
     */
    public function transfers(): HasMany
    {
        return $this->hasMany(PrescriptionTransfer::class);
    }

    /**
     * Muddati o'tganmi (7.11) — buyurtma ochishda ogohlantirish uchun.
     *
     * Bu **taqiq emas**: eski retsept bo'yicha ham buyurtma berish
     * mumkin, faqat sotuvchi qayta ko'rikni tavsiya qilishi kerak.
     */
    public function isExpired(): bool
    {
        return $this->valid_until->isPast();
    }

    /**
     * Retsept necha oylik — ogohlantirish matni uchun.
     */
    public function ageInMonths(): int
    {
        return (int) ($this->created_at?->diffInMonths(now()) ?? 0);
    }

    /**
     * Oldingi retseptdan sezilarli farq qilyaptimi (§10, shifokor
     * ekrani: "Yangi qiymat eskisidan 1.5+ farq qilsa — ogohlantirish").
     *
     * Sabab: bir zarbda katta o'zgargan diopter ko'pincha o'lchov
     * xatosi bo'ladi, kasallik emas — shifokor qayta tekshirib
     * ko'rishi kerak.
     */
    public function hasSignificantJumpFrom(?self $previous): bool
    {
        if (! $previous instanceof self) {
            return false;
        }

        $threshold = self::changeThreshold();

        foreach (['od_sph', 'od_cyl', 'os_sph', 'os_cyl'] as $column) {
            $now = $this->getAttribute($column);
            $before = $previous->getAttribute($column);

            // Bo'sh yoki raqam bo'lmagan qiymat solishtirilmaydi:
            // ogohlantirish mexanizmi retsept saqlanishiga to'sqinlik
            // qilmasligi kerak.
            if (! is_string($now) || ! is_string($before) || ! is_numeric($now) || ! is_numeric($before)) {
                continue;
            }

            $difference = bcsub($now, $before, 2);
            $absolute = bccomp($difference, '0', 2) < 0 ? bcsub('0', $difference, 2) : $difference;

            if (bccomp($absolute, $threshold, 2) >= 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Shu filialdagi ochiq tiketlar — sotuvchi ekranining ro'yxati (7.11).
     *
     * @param  Builder<$this>  $query
     * @param  array<int, int>  $branchIds
     */
    public function scopeActiveTicketsFor(Builder $query, array $branchIds): void
    {
        $query->where('ticket_active', true)->whereIn('ticket_branch_id', $branchIds);
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeActiveTickets(Builder $query): void
    {
        $query->where('ticket_active', true);
    }

    /**
     * Ogohlantirish chegarasi — §15, `config/optika.php`.
     *
     * Sozlama buzilgan bo'lsa standart qiymatga qaytamiz: noto'g'ri
     * konfiguratsiya tufayli retsept saqlanmay qolishi mumkin emas.
     *
     * @return numeric-string
     */
    private static function changeThreshold(): string
    {
        $configured = config('optika.prescriptions.diopter_change_warning', '1.50');

        return is_string($configured) && is_numeric($configured) ? $configured : '1.50';
    }
}
