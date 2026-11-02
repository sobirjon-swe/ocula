<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Models;

use Illuminate\Database\Eloquent\Model;

/** @property int $id
 * @property string $status
 * @property int $attempts
 */
class StaffNotification extends Model
{
    protected $fillable = ['dedupe_key', 'chat_id', 'locale', 'message', 'status', 'attempts', 'error_code', 'sent_at'];

    protected function casts(): array
    {
        return ['chat_id' => 'integer', 'attempts' => 'integer', 'sent_at' => 'immutable_datetime'];
    }
}
