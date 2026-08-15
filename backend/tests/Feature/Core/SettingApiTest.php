<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Modules\Core\Enums\Role;
use App\Modules\Core\Enums\SettingKey;
use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

/**
 * Tizim sozlamalari — SCHEMA.md §1, PERMISSIONS.md §1.
 *
 * Asosiy kafolatlar: ro'yxat har doim to'liq (jadvalda yozuv bo'lmasa
 * `config/optika.php` dagi standart); faqat direktor o'zgartiradi;
 * noma'lum kalit umuman o'tmaydi.
 */
final class SettingApiTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();
    }

    /**
     * Jadval bo'sh bo'lsa ham ekran haqiqiy qiymatlarni ko'rsatishi
     * kerak — "sozlanmagan" degan yolg'on taassurot bo'lmasin.
     */
    #[Test]
    public function the_list_falls_back_to_the_config_defaults(): void
    {
        $this->actingAsDirector();

        $this->assertDatabaseCount('settings', 0);

        $this->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.money_rounding_step', config('optika.money.rounding_step'))
            ->assertJsonPath('data.discount_limit_percent', config('optika.orders.discount_limit_percent'))
            ->assertJsonPath('data.debt_reminder_days', config('optika.debts.reminder_days'));
    }

    #[Test]
    public function the_director_updates_several_settings_at_once(): void
    {
        $director = $this->actingAsDirector();

        $this->putJson('/api/v1/settings', [
            'min_prepayment_percent' => 30,
            'discount_limit_percent' => 5,
        ])->assertOk()
            ->assertJsonPath('data.min_prepayment_percent', 30)
            ->assertJsonPath('data.discount_limit_percent', 5);

        $this->assertSame(30, Setting::valueFor(SettingKey::MinPrepaymentPercent));
        $this->assertSame($director->id, Setting::query()
            ->where('key', SettingKey::MinPrepaymentPercent->value)
            ->firstOrFail()
            ->updated_by);

        // Yuborilmagan kalitlar tegilmaydi.
        $this->assertDatabaseMissing('settings', ['key' => SettingKey::MoneyRoundingStep->value]);
    }

    #[Test]
    public function an_unknown_key_is_simply_ignored(): void
    {
        $this->actingAsDirector();

        $this->putJson('/api/v1/settings', [
            'min_prepayment_percentt' => 30,
        ])->assertOk();

        $this->assertDatabaseCount('settings', 0);
    }

    #[Test]
    public function a_value_outside_its_range_is_refused(): void
    {
        $this->actingAsDirector();

        $this->putJson('/api/v1/settings', ['discount_limit_percent' => 150])
            ->assertStatus(422)
            ->assertJsonValidationErrors('discount_limit_percent');

        $this->putJson('/api/v1/settings', ['debt_reminder_days' => ['ertaga']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('debt_reminder_days.0');

        $this->assertDatabaseCount('settings', 0);
    }

    #[Test]
    public function a_manager_may_read_but_not_change_the_settings(): void
    {
        $this->actingAsEmployee(Role::BranchManager, Branch::factory()->create());

        $this->getJson('/api/v1/settings')->assertOk();

        $this->putJson('/api/v1/settings', ['discount_limit_percent' => 5])->assertForbidden();
    }

    #[Test]
    public function a_seller_may_not_even_read_them(): void
    {
        $this->actingAsEmployee(Role::Seller, Branch::factory()->create());

        $this->getJson('/api/v1/settings')->assertForbidden();
    }

    /**
     * Sozlama haqiqatan **ishlaydi**: chegirma limiti config'dan emas,
     * jadvaldan olinadi (`CreateOrder::approverFor`).
     */
    #[Test]
    public function the_stored_discount_limit_overrides_the_config_default(): void
    {
        $this->actingAsDirector();

        $this->assertSame(
            config('optika.orders.discount_limit_percent'),
            Setting::valueFor(SettingKey::DiscountLimitPercent),
        );

        Setting::put(SettingKey::DiscountLimitPercent, 25);

        $this->assertSame(25, Setting::valueFor(SettingKey::DiscountLimitPercent));
    }
}
