<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Models;

use App\Modules\Sales\Models\Customer;
use App\Modules\Telegram\Enums\NotificationStatus;
use App\Modules\Telegram\Enums\NotificationType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Yuborilgan Telegram xabari — BOSQICH-7.md §4, §5 #2.
 *
 * Faqat jurnal (log), moliyaviy hujjat emas (7.21) — `Immutable` trait
 * qo'llanmagan. `dedupe_key` avtomatik (scheduled) eslatmalarni bir
 * martalik qiladi; qo'lda yuborilgan xabarlarda `null`.
 *
 * @property int $id
 * @property int $customer_id
 * @property int $chat_id
 * @property NotificationType $type
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string|null $dedupe_key
 * @property string $message
 * @property NotificationStatus $status
 * @property string|null $error
 * @property CarbonImmutable $created_at
 */
class TelegramNotification extends Model
{
    protected $table = 'telegram_notifications';

    protected $fillable = [
        'customer_id', 'chat_id', 'type', 'source_type', 'source_id',
        'dedupe_key', 'message', 'status', 'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'chat_id' => 'integer',
            'type' => NotificationType::class,
            'source_id' => 'integer',
            'status' => NotificationStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
