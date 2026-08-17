<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Modules\Core\Models\User;
use App\Modules\Finance\Enums\ReminderChannel;
use App\Modules\Finance\Enums\ReminderResponse;
use App\Support\Concerns\Immutable;
use Carbon\CarbonImmutable;
use Database\Factories\DebtReminderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Qarz eslatmasi jurnali (CRM) — SCHEMA.md, BOSQICH-7.md §5 #2,
 * BOSQICH-10.md §10a.
 *
 * `telegram_notifications` "yuborildimi" degan texnik savolga javob
 * beradi, bu jadval esa "nima bo'ldi" degan CRM savoliga — shuning
 * uchun ikkalasi ham saqlanadi, biri ikkinchisini almashtirmaydi.
 *
 * Yozuv o'zgarmaydi: javob keyinroq bilinsa, **yangi** yozuv qo'shiladi
 * (bir eslatma — bir voqea), tahrirlanmaydi.
 *
 * @property int $id
 * @property int $debt_id
 * @property CarbonImmutable $sent_at
 * @property ReminderChannel $channel
 * @property ReminderResponse $response
 * @property CarbonImmutable|null $responded_at
 * @property string|null $note
 * @property int|null $created_by
 */
class DebtReminder extends Model
{
    /** @use HasFactory<DebtReminderFactory> */
    use HasFactory, Immutable;

    /**
     * Jadvalda `created_at`/`updated_at` yo'q — o'rniga `sent_at` bor.
     */
    public $timestamps = false;

    protected $fillable = [
        'debt_id', 'sent_at', 'channel', 'response', 'responded_at', 'note', 'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'channel' => ReminderChannel::class,
            'response' => ReminderResponse::class,
            'responded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Debt, $this>
     */
    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
