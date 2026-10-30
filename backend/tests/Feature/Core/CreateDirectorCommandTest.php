<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateDirectorCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_director_with_hidden_confirmed_password(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $branch = Branch::factory()->create(['code' => 'A', 'name' => 'Main branch']);

        $this->artisan('optika:create-director')
            ->expectsQuestion('Name', 'Initial director')
            ->expectsQuestion('Phone (international format, e.g. +998901234567)', '+998901234567')
            ->expectsChoice('Branch', 'A — Main branch', ['A — Main branch'])
            ->expectsQuestion('Password (at least 12 characters)', 'Strong-test-password-42')
            ->expectsQuestion('Confirm password', 'Strong-test-password-42')
            ->assertSuccessful();

        $user = User::query()->where('phone', '+998901234567')->sole();
        $this->assertTrue($user->hasRole('director'));
        $this->assertSame($branch->id, $user->branch_id);
        $this->assertTrue(Hash::check('Strong-test-password-42', $user->password));
    }

    public function test_refuses_existing_phone_without_promoting_existing_account(): void
    {
        $user = User::factory()->create(['phone' => '+998901234567']);
        $this->artisan('optika:create-director')
            ->expectsQuestion('Name', 'Another person')
            ->expectsQuestion('Phone (international format, e.g. +998901234567)', '+998901234567')
            ->expectsOutput('This phone already exists. No account was changed.')
            ->assertFailed();
        $this->assertFalse($user->fresh()->hasRole('director'));
        $this->assertSame(1, User::query()->count());
    }

    public function test_rejects_password_mismatch_without_creating_user(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Branch::factory()->create(['code' => 'A', 'name' => 'Main branch']);
        $this->artisan('optika:create-director')
            ->expectsQuestion('Name', 'Initial director')
            ->expectsQuestion('Phone (international format, e.g. +998901234567)', '+998901234567')
            ->expectsChoice('Branch', 'A — Main branch', ['A — Main branch'])
            ->expectsQuestion('Password (at least 12 characters)', 'Strong-test-password-42')
            ->expectsQuestion('Confirm password', 'Different-password-42')
            ->assertFailed();
        $this->assertSame(0, User::query()->count());
    }
}
