<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Models;

use App\Modules\Core\Models\User;
use App\Modules\Sales\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mijozni botga bog'lash kodi — BOSQICH-7.md §3.
 *
 * Mijoz botga `/start <code>` yuboradi, webhook shu yozuvni topib
 * `customers.telegram_id` ni to'ldiradi va kodni bir martalik qiladi
 * (`used_at`).
 *
 * @property int $id
 * @property int $customer_id
 * @property string $code
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $used_at
 * @property int $created_by
 */
class TelegramLinkCode extends Model
{
    protected $table = 'telegram_link_codes';

    protected $fillable = ['customer_id', 'code', 'expires_at', 'used_at', 'created_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
