<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Core\Models\Branch;
use App\Modules\Core\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

final class CreateDirector extends Command
{
    protected $signature = 'optika:create-director';

    protected $description = 'Create a new director interactively, without default credentials';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Run this command interactively. Passwords must never be command-line arguments.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Name'));
        $phone = trim((string) $this->ask('Phone (international format, e.g. +998901234567)'));
        $validator = Validator::make(['name' => $name, 'phone' => $phone], [
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/'],
        ]);
        if ($validator->fails()) {
            $this->error('Enter a name and a valid international phone number.');

            return self::FAILURE;
        }

        // Include soft-deleted accounts: this tool must never promote an existing identity.
        if (User::withTrashed()->where('phone', $phone)->exists()) {
            $this->error('This phone already exists. No account was changed.');

            return self::FAILURE;
        }
        if (! Role::query()->where('name', 'director')->where('guard_name', 'web')->exists()) {
            $this->error('Seed roles before creating the first director.');

            return self::FAILURE;
        }

        $branches = Branch::query()->where('is_active', true)->orderBy('code')->get();
        if ($branches->isEmpty()) {
            $this->error('Create an active branch before creating the director.');

            return self::FAILURE;
        }
        $choices = $branches->map(fn (Branch $branch): string => $branch->code.' — '.$branch->name)->all();
        $selected = $this->choice('Branch', $choices);
        $index = array_search($selected, $choices, true);
        if ($index === false) {
            return self::FAILURE;
        }

        // Disable the visible fallback: fail closed if the terminal cannot hide input.
        $password = (string) $this->secret('Password (at least 12 characters)', false);
        $confirmation = (string) $this->secret('Confirm password', false);
        if (mb_strlen($password) < 12 || ! hash_equals($password, $confirmation)) {
            $this->error('Use at least 12 characters and matching password confirmation. No account was created.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($name, $phone, $password, $branches, $index): void {
            $user = User::query()->create([
                'name' => $name, 'phone' => $phone, 'password' => $password,
                'branch_id' => $branches[$index]->id, 'is_active' => true, 'locale' => 'uz-latn',
            ]);
            $user->assignRole('director');
        });

        $this->info('Director created. Sign in with the phone and password you just supplied.');

        return self::SUCCESS;
    }
}
