<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Core\Enums\DeviceType;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => 'Planshet',
            'type' => DeviceType::Tablet,
            'token' => Str::random(48),
            'allowed_roles' => ['doctor', 'master'],
            'is_active' => true,
        ];
    }
}
