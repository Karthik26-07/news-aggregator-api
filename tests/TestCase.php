<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{

    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure the testing environment is loaded
        $this->app->loadEnvironmentFrom('.env.testing');

        // Optional: Set any additional environment variables programmatically
        config(['app.name' => 'News Aggregator LaravelTest']);
        config(['queue.default' => 'sync']); // Ensures queues run synchronously in tests
    }
}
