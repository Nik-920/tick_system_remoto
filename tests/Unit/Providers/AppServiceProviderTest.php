<?php

namespace Tests\Unit\Providers;

use App\Providers\AppServiceProvider;
use RuntimeException;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    public function test_boot_throws_when_staging_or_production_falls_back_to_sqlite(): void
    {
        $originalEnv = $this->app['env'];

        $this->app['env'] = 'production';
        config([
            'database.default' => 'sqlite',
            'database.allow_sqlite_in_production' => false,
        ]);

        $provider = new AppServiceProvider($this->app);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('fallback a sqlite');

            $provider->boot();
        } finally {
            $this->app['env'] = $originalEnv;
        }
    }

    public function test_boot_does_not_throw_when_sqlite_guard_is_explicitly_disabled(): void
    {
        $originalEnv = $this->app['env'];

        $this->app['env'] = 'production';
        config([
            'database.default' => 'sqlite',
            'database.allow_sqlite_in_production' => true,
        ]);

        $provider = new AppServiceProvider($this->app);

        try {
            $provider->boot();

            $this->assertTrue(true);
        } finally {
            $this->app['env'] = $originalEnv;
        }
    }
}
