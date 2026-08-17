<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Finance\Enums\ReminderChannel;
use App\Modules\Finance\Enums\ReminderResponse;
use App\Modules\Finance\Models\Debt;
use App\Modules\Finance\Models\DebtReminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DebtReminder>
 */
class DebtReminderFactory extends Factory
{
    protected $model = DebtReminder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'debt_id' => Debt::factory(),
            'sent_at' => now(),
            'channel' => ReminderChannel::Call->value,
            'response' => ReminderResponse::None->value,
        ];
    }
}
