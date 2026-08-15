<?php

declare(strict_types=1);

namespace App\Modules\Core\Actions\Device;

use App\Modules\Core\Enums\DeviceType;
use App\Modules\Core\Models\Device;
use Illuminate\Support\Str;

/**
 * Qurilmani ro'yxatdan o'tkazish — PROJECT.md 7.14.
 *
 * Token **bir marta** ochiq holda qaytariladi va bazada faqat hash
 * bo'lib qoladi (`Device::$casts` da `hashed`). Yo'qolsa qayta
 * ko'rsatib bo'lmaydi — qurilma qaytadan ro'yxatdan o'tkaziladi.
 * Bu ataylab: bazaga kirgan odam planshet nomidan kira olmasin.
 *
 * `allowed_roles` PIN bilan kirishni toraytiradi: ustaxonadagi
 * planshetda faqat usta almashadi, kassa POS'ida faqat sotuvchi
 * (`AuthenticateWithPin` shu ro'yxatga qaraydi).
 */
final class RegisterDevice
{
    /** Token uzunligi — ustun `string(128)`, hash undan qisqaroq. */
    private const int TOKEN_LENGTH = 48;

    /**
     * @param  array<int, string>  $allowedRoles
     * @return array{0: Device, 1: string} Qurilma va **bir marta**
     *                                     ko'rsatiladigan ochiq token
     */
    public function handle(
        int $branchId,
        string $name,
        DeviceType $type,
        array $allowedRoles = [],
    ): array {
        $token = Str::random(self::TOKEN_LENGTH);

        $device = Device::create([
            'branch_id' => $branchId,
            'name' => $name,
            'type' => $type,
            'token' => $token,
            'allowed_roles' => array_values(array_unique($allowedRoles)),
            'is_active' => true,
        ]);

        return [$device, $token];
    }
}
