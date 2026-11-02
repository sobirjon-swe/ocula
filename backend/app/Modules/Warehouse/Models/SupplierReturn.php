<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Models;

use App\Support\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;

/** @property int $id
 * @property int $defect_id
 * @property int|null $purchase_id
 * @property int|null $supplier_id
 * @property string $amount
 * @property string $status
 */
class SupplierReturn extends Model
{
    use BelongsToBranch;

    protected $guarded = ['id'];
}
