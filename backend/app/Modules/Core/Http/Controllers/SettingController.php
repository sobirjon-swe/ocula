<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Core\Enums\SettingKey;
use App\Modules\Core\Http\Requests\Setting\UpdateSettingsRequest;
use App\Modules\Core\Models\Setting;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tizim sozlamalari — SCHEMA.md §1, PERMISSIONS.md §1.
 *
 * Ro'yxat **har doim to'liq** qaytadi: jadvalda yozuv bo'lmagan kalit
 * uchun `config/optika.php` dagi standart qiymat ko'rsatiladi. Aks
 * holda ekran bo'sh maydon ko'rsatib, direktor "sozlanmagan" deb
 * o'ylardi — aslida qiymat bor va ishlab turibdi.
 *
 * Kalitlar `SettingKey` enumida qat'iy belgilangan, shuning uchun bu
 * yerda `store` va `destroy` yo'q.
 */
final class SettingController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        return ApiResponse::data($this->payload());
    }

    /**
     * Bir nechta sozlamani birga saqlaydi — ekran ham shunday ishlaydi.
     */
    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $this->authorize('manage', Setting::class);

        $editorId = $this->currentUser($request)->id;
        $validated = $request->validated();

        foreach (SettingKey::cases() as $key) {
            if (! array_key_exists($key->value, $validated)) {
                continue;
            }

            Setting::put($key, $validated[$key->value], $editorId);
        }

        return ApiResponse::data($this->payload());
    }

    /**
     * Kalit → qiymat, standartlar bilan to'ldirilgan holda.
     *
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return Setting::resolved();
    }
}
