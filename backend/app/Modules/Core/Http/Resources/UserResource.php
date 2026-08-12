<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Resources;

use App\Modules\Core\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Xodim — PROJECT.md §9 javob formati.
 *
 * `pin_hash` va `password` bu yerga umuman tushmaydi (modelda `$hidden`),
 * `debt_limit` esa faqat uni ko'rish huquqi borlarga chiqadi (7.6).
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'branch_id' => $this->branch_id,
            // Direktorda `branch_id` bo'lmasligi mumkin (§4) — bog'lanish
            // yuklangan, lekin qiymati `null` bo'lgan holat oddiy hol.
            'branch' => $this->when(
                $this->relationLoaded('branch'),
                fn (): ?BranchResource => $this->branch === null
                    ? null
                    : new BranchResource($this->branch),
            ),
            'locale' => $this->locale->value,
            'is_active' => $this->is_active,
            'has_pin' => $this->hasPin(),
            'debt_limit' => $this->when(
                $request->user()?->can('core.user.set_debt_limit') ?? false,
                fn (): string => $this->debt_limit->toString(),
            ),
            'roles' => $this->whenLoaded(
                'roles',
                fn (): array => $this->getRoleNames()->all(),
            ),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
