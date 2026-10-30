<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Jobs\NotifyDuplicateProducts;
use App\Modules\Catalog\Jobs\NotifyPendingProduct;
use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Enums\Role;
use App\Modules\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsEmployee;
use Tests\TestCase;

final class StaffCatalogNotificationsTest extends TestCase
{
    use ActsAsEmployee, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPermissions();
        $this->actingAsDirector()->update(['locale' => 'ru']);
        config(['services.telegram.director_chat_id' => '123456']);
        Http::preventStrayRequests();
        Http::fake(['*sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
        Queue::fake();
    }

    #[Test]
    public function failed_staff_delivery_retries_with_a_safe_journal_and_cyrillic_director_locale(): void
    {
        $this->actingAsDirector()->update(['locale' => 'uz-cyrl']);
        // The first director is the configured recipient locale source.
        User::role('director', 'web')->update(['locale' => 'uz-cyrl']);
        $product = Product::factory()->create(['name' => 'Test', 'status' => 'pending']);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*sendMessage' => Http::sequence()
            ->push(['ok' => false, 'description' => 'bot-secret must not reach the journal'], 500)
            ->push(['ok' => true, 'result' => ['message_id' => 1]])]);
        $job = new NotifyPendingProduct($product->id);
        try {
            app()->call([$job, 'handle']);
            $this->fail('A failed delivery must trigger queue retry.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Staff notification delivery failed.', $exception->getMessage());
        }
        $this->assertDatabaseHas('staff_notifications', ['dedupe_key' => 'pending-product:'.$product->id, 'status' => 'failed', 'error_code' => 'delivery_failed', 'attempts' => 1]);
        app()->call([$job, 'handle']);
        $this->assertDatabaseHas('staff_notifications', ['dedupe_key' => 'pending-product:'.$product->id, 'status' => 'sent', 'locale' => 'uz-cyrl', 'attempts' => 2]);
        Http::assertSent(fn ($request): bool => str_contains($request['text'], 'Янги товар'));
    }

    #[Test]
    public function pending_product_notification_is_queued_after_commit_localized_and_sent_once(): void
    {
        $product = Product::factory()->create(['name' => 'New Ray Ban', 'status' => 'pending']);
        Queue::assertPushed(NotifyPendingProduct::class, fn (NotifyPendingProduct $job): bool => $job->afterCommit === true);
        $job = new NotifyPendingProduct($product->id);
        app()->call([$job, 'handle']);
        app()->call([$job, 'handle']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => str_contains($request['text'], 'Новый товар') && $request['chat_id'] === 123456);
        $this->assertDatabaseHas('staff_notifications', ['dedupe_key' => 'pending-product:'.$product->id, 'status' => 'sent', 'locale' => 'ru']);
    }

    #[Test]
    public function duplicate_pairs_are_reviewable_and_weekly_notifications_are_deduplicated(): void
    {
        $left = Product::factory()->create(['name' => 'Ray Ban 3025 black', 'type' => 'frame']);
        $right = Product::factory()->create(['name' => 'Ray Ban 3025 black edition', 'type' => 'frame']);
        Product::factory()->create(['name' => 'Unrelated cleaning solution', 'type' => 'accessory']);
        $this->getJson('/api/v1/products/duplicates')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.left_id', $left->id)->assertJsonPath('data.0.right_id', $right->id);
        $this->artisan('catalog:report-duplicates')->assertSuccessful();
        $this->artisan('catalog:report-duplicates')->assertSuccessful();
        Queue::assertPushed(NotifyDuplicateProducts::class, 2);
        $job = new NotifyDuplicateProducts(now('Asia/Tashkent')->format('o-\WW'));
        app()->call([$job, 'handle']);
        app()->call([$job, 'handle']);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('staff_notifications', ['dedupe_key' => 'catalog-duplicates:'.now('Asia/Tashkent')->format('o-\WW'), 'status' => 'sent']);
        $this->actingAsEmployee(Role::Seller);
        $this->getJson('/api/v1/products/duplicates')->assertForbidden();
    }
}
