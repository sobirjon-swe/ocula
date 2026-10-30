<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Models;

use App\Support\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $id
 * @property int $branch_id
 * @property int $location_id
 * @property string $status
 * @property int $snapshot_movement_id
 */
class Inventory extends Model
{
    use BelongsToBranch;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['started_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime'];
    }

    /** @return HasMany<InventoryItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }
}
