<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Location;
use App\Modules\Core\Models\User;
use App\Modules\Warehouse\Enums\DefectReason;
use App\Modules\Warehouse\Models\Defect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Defect>
 */
class DefectFactory extends Factory
{
    protected $model = Defect::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $branch = Branch::factory();

        return [
            'branch_id' => $branch,
            'location_id' => Location::factory()->for($branch),
            'variant_id' => ProductVariant::factory(),
            'quantity' => 1,
            'reason' => DefectReason::MasterError,
            'cost_impact' => '0.00',
            'note' => 'Test brak',
            'reported_by' => User::factory(),
        ];
    }
}
