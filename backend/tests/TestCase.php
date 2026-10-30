<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\PendingCommand;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    /** @param string $command
     * @param  array<string, mixed>  $parameters
     */
    public function artisan($command, $parameters = []): PendingCommand
    {
        $pending = parent::artisan($command, $parameters);
        if (! $pending instanceof PendingCommand) {
            throw new \LogicException('These tests require mocked console output for command expectations.');
        }

        return $pending;
    }
}
