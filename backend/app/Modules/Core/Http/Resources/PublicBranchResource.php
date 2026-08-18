<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Resources;

use App\Modules\Core\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Filial — landing sayt uchun ochiq ma'lumot — BOSQICH-11.md.
 *
 * Ataylab `BranchResource` dan alohida: bu yerga faqat mehmonga
 * ko'rsatsa bo'ladigan maydonlar chiqadi — ichki `BranchResource` ga
 * keyinchalik ichki maydon qo'shilsa ham, bu yerga sizib chiqmaydi.
 *
 * @mixin Branch
 */
class PublicBranchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'phone' => $this->phone,
            'open_time' => $this->open_time,
            'close_time' => $this->close_time,
            'lat' => $this->lat,
            'lng' => $this->lng,
        ];
    }
}
