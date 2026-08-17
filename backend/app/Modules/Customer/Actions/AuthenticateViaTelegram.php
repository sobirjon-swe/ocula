<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Modules\Sales\Models\Customer;
use App\Modules\Telegram\Services\InitDataValidator;
use App\Support\Enums\Locale;
use Illuminate\Support\Facades\DB;

/**
 * Telegram `initData` orqali kirish — PROJECT.md §9, §10, BOSQICH-9.md §3.
 *
 * "Ro'yxatdan o'tish formasi YO'Q" (§10) — mijoz `telegram_id` bo'yicha
 * find-or-create qilinadi. Birinchi marta kelgan mijozda `phone` **yo'q**
 * (Telegram buni bermaydi) — u keyinroq profil orqali o'zi kiritadi
 * (`customer.profile.update`).
 */
final class AuthenticateViaTelegram
{
    public function __construct(private readonly InitDataValidator $validator) {}

    /**
     * @return array{customer: Customer, token: string}|null
     */
    public function handle(string $initData): ?array
    {
        $fields = $this->validator->validate($initData);

        if ($fields === null) {
            return null;
        }

        /** @var array<string, mixed> $telegramUser */
        $telegramUser = json_decode($fields['user'] ?? '{}', true) ?? [];
        $telegramId = (int) ($telegramUser['id'] ?? 0);

        if ($telegramId <= 0) {
            return null;
        }

        $customer = DB::transaction(function () use ($telegramId, $telegramUser): Customer {
            /** @var Customer $customer */
            $customer = Customer::query()->firstOrCreate(
                ['telegram_id' => $telegramId],
                [
                    'name' => $this->displayName($telegramUser),
                    'locale' => $this->localeFor($telegramUser),
                    'first_visit_at' => now(),
                ],
            );

            if ($customer->wasRecentlyCreated) {
                $customer->assignRole('customer');
            }

            return $customer;
        });

        return [
            'customer' => $customer,
            'token' => $customer->createToken('mini-app')->plainTextToken,
        ];
    }

    /**
     * @param  array<string, mixed>  $telegramUser
     */
    private function displayName(array $telegramUser): string
    {
        $name = trim(($telegramUser['first_name'] ?? '').' '.($telegramUser['last_name'] ?? ''));

        return $name !== '' ? $name : __('customer::auth.default_name');
    }

    /**
     * @param  array<string, mixed>  $telegramUser
     */
    private function localeFor(array $telegramUser): string
    {
        $code = (string) ($telegramUser['language_code'] ?? '');

        return match ($code) {
            'ru' => Locale::Ru->value,
            'en' => Locale::En->value,
            default => Locale::default()->value,
        };
    }
}
