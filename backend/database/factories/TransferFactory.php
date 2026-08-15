<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Enums\DeliveryMethod;
use App\Modules\Warehouse\Enums\TransferStatus;
use App\Modules\Warehouse\Models\Transfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transfer>
 */
class TransferFactory extends Factory
{
    protected $model = Transfer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'T-'.fake()->unique()->numerify('2608-#####'),
            'from_location_id' => Location::factory(),
            'to_location_id' => Location::factory(),
            'status' => TransferStatus::Draft,
            'delivery_method' => DeliveryMethod::OwnDriver,
            'created_by' => User::factory(),
        ];
    }
}
