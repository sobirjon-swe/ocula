<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use Database\Seeders\DemoUserSeeder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DemoSeederSafetyTest extends TestCase
{
    #[Test]
    public function direct_demo_seeding_is_refused_in_production_before_any_database_write(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('only in local/testing');
        (new DemoUserSeeder)->run();
    }
}
