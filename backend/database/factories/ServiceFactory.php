<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Enums\ServiceType;
use App\Modules\Catalog\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'price' => '50000.00',
            'duration_min' => 20,
            'type' => ServiceType::Exam,
            'is_active' => true,
        ];
    }
}
