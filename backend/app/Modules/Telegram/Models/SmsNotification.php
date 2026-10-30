<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Models;

use App\Modules\Telegram\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Model;

/** @property NotificationStatus $status */
class SmsNotification extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => NotificationStatus::class];
    }
}
