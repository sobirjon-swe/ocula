<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Core\Enums\LocationType;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'type' => LocationType::Warehouse,
            'name' => 'Ombor',
            'is_active' => true,
        ];
    }
}
