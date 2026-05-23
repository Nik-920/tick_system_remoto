<?php

namespace Tests\Unit\Providers;

use App\Providers\EventServiceProvider;
use Tests\TestCase;

class EventServiceProviderTest extends TestCase
{
    public function test_should_discover_events_returns_false(): void
    {
        $provider = new EventServiceProvider($this->app);

        $this->assertFalse($provider->shouldDiscoverEvents());
    }
}
