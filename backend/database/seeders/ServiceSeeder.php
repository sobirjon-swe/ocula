<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Enums\ServiceType;
use App\Modules\Catalog\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Boshlang'ich xizmatlar — PROJECT.md §6.2.
 *
 * Katalog (tovarlar) bo'sh boshlanadi va sotuv paytida to'ldiriladi
 * (7.13), lekin xizmatlar ro'yxati qisqa va oldindan ma'lum.
 */
class ServiceSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, price: string, type: ServiceType, duration: int|null}>
     */
    private const array SERVICES = [
        ['name' => "Shifokor ko'rigi", 'price' => '50000.00', 'type' => ServiceType::Exam, 'duration' => 20],
        ['name' => "Linza o'rnatish", 'price' => '30000.00', 'type' => ServiceType::Assembly, 'duration' => 30],
        ['name' => "Ko'zoynak ta'miri", 'price' => '20000.00', 'type' => ServiceType::Repair, 'duration' => 15],
        ['name' => 'Sozlash', 'price' => '0.00', 'type' => ServiceType::Repair, 'duration' => 5],
    ];

    public function run(): void
    {
        foreach (self::SERVICES as $service) {
            Service::query()->firstOrCreate(
                ['name' => $service['name']],
                [
                    'price' => $service['price'],
                    'type' => $service['type'],
                    'duration_min' => $service['duration'],
                    'is_active' => true,
                ],
            );
        }
    }
}
