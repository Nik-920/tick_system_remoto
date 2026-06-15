<?php

declare(strict_types=1);

namespace Tests\Feature\Cache;

use App\Events\TicketCreated;
use App\Events\TicketStateChanged;
use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Cache\DashboardCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Cache behaviour for the reporter dashboard (pilot surface).
 *
 * Driver: array (phpunit.xml → CACHE_STORE=array). All cache operations are
 * in-memory; no Redis required. Each test starts with a flushed cache so
 * version keys never bleed across methods.
 *
 * Coverage:
 *   - miss / hit (callback executed once)
 *   - versioned keys isolate users (no cross-user leak)
 *   - invalidation bumps version → forces re-query
 *   - user-A invalidation does not affect user-B
 *   - different context hashes produce different entries
 *   - DASHBOARD_CACHE_ENABLED=false bypasses the cache completely
 *   - TicketCreated event invalidates the reporter's own cache
 *   - TicketStateChanged event invalidates the reporter's cache
 *   - Marking notifications as read does NOT invalidate dashboard cache
 *   - contextHash is deterministic and context-sensitive
 *   - Full HTTP route uses cache correctly (second request served from cache)
 */
class ReporterDashboardCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // ── Unit: DashboardCache service ──────────────────────────────

    public function test_callback_is_executed_on_cache_miss(): void
    {
        $cache = new DashboardCache;
        $calls = 0;

        $result = $cache->rememberReporter('u-1', ['surface' => 'test'], 60, function () use (&$calls) {
            $calls++;

            return ['data' => 'value'];
        });

        $this->assertSame(1, $calls);
        $this->assertSame(['data' => 'value'], $result);
    }

    public function test_callback_is_not_called_on_cache_hit(): void
    {
        $cache = new DashboardCache;
        $calls = 0;

        $cb = function () use (&$calls): array {
            $calls++;

            return ['data' => 'value'];
        };

        $cache->rememberReporter('u-1', ['surface' => 'test'], 60, $cb);
        $cache->rememberReporter('u-1', ['surface' => 'test'], 60, $cb);

        $this->assertSame(1, $calls, 'Second call should hit cache, not execute callback');
    }

    public function test_different_users_receive_different_cache_entries(): void
    {
        $cache = new DashboardCache;

        $calls = ['u-1' => 0, 'u-2' => 0];

        $cache->rememberReporter('u-1', [], 60, function () use (&$calls): array {
            $calls['u-1']++;

            return ['user' => 'one'];
        });

        $cache->rememberReporter('u-2', [], 60, function () use (&$calls): array {
            $calls['u-2']++;

            return ['user' => 'two'];
        });

        // Both miss (separate version keys) → both callbacks run
        $this->assertSame(1, $calls['u-1']);
        $this->assertSame(1, $calls['u-2']);

        // Second hit for each: callbacks must NOT run again
        $cache->rememberReporter('u-1', [], 60, function () use (&$calls): array {
            $calls['u-1']++;

            return [];
        });
        $cache->rememberReporter('u-2', [], 60, function () use (&$calls): array {
            $calls['u-2']++;

            return [];
        });

        $this->assertSame(1, $calls['u-1']);
        $this->assertSame(1, $calls['u-2']);
    }

    public function test_invalidation_forces_callback_re_execution(): void
    {
        $cache = new DashboardCache;
        $calls = 0;

        $cb = function () use (&$calls): array {
            $calls++;

            return ['call' => $calls];
        };

        $cache->rememberReporter('u-1', [], 60, $cb);  // miss → call #1
        $cache->invalidateReporter('u-1');
        $cache->rememberReporter('u-1', [], 60, $cb);  // miss after invalidation → call #2

        $this->assertSame(2, $calls);
    }

    public function test_invalidating_user_a_does_not_affect_user_b(): void
    {
        $cache = new DashboardCache;
        $callsB = 0;

        $cache->rememberReporter('u-1', [], 60, fn (): array => []);
        $cache->rememberReporter('u-2', [], 60, function () use (&$callsB): array {
            $callsB++;

            return [];
        });

        $cache->invalidateReporter('u-1'); // only u-1 invalidated

        // u-2 second hit: should still be cached
        $cache->rememberReporter('u-2', [], 60, function () use (&$callsB): array {
            $callsB++;

            return [];
        });

        $this->assertSame(1, $callsB, 'u-2 cache must remain intact when u-1 is invalidated');
    }

    public function test_different_context_produces_separate_cache_entries(): void
    {
        $cache = new DashboardCache;
        $calls = 0;

        $cb = function () use (&$calls): array {
            $calls++;

            return [];
        };

        $cache->rememberReporter('u-1', ['surface' => 'A'], 60, $cb);
        $cache->rememberReporter('u-1', ['surface' => 'B'], 60, $cb);

        $this->assertSame(2, $calls, 'Different context must produce different cache keys');
    }

    public function test_cache_is_bypassed_when_dashboard_cache_disabled(): void
    {
        config(['cache.dashboard_enabled' => false]);

        $cache = new DashboardCache;
        $calls = 0;

        $cb = function () use (&$calls): array {
            $calls++;

            return [];
        };

        $cache->rememberReporter('u-1', [], 60, $cb);
        $cache->rememberReporter('u-1', [], 60, $cb);

        $this->assertSame(2, $calls, 'Disabled cache must call the callback every time');
    }

    public function test_context_hash_is_deterministic_and_context_sensitive(): void
    {
        $cache = new DashboardCache;

        $h1 = $cache->contextHash(['surface' => 'reporter_dashboard', 'page' => 1]);
        $h2 = $cache->contextHash(['surface' => 'reporter_dashboard', 'page' => 2]);
        $h3 = $cache->contextHash(['surface' => 'reporter_dashboard', 'page' => 1]); // same as h1
        $h4 = $cache->contextHash(['page' => 1, 'surface' => 'reporter_dashboard']);  // ksorted, same as h1

        $this->assertSame($h1, $h3, 'Hash must be deterministic');
        $this->assertSame($h1, $h4, 'Hash must be key-order-independent');
        $this->assertNotSame($h1, $h2, 'Different context must produce different hash');
    }

    public function test_version_key_is_bumped_after_each_invalidation(): void
    {
        $cache = new DashboardCache;

        // Initialise version (first rememberReporter sets it to 1)
        $cache->rememberReporter('u-1', [], 60, fn (): array => []);
        $this->assertSame(1, Cache::get('dashboard:version:reporter:u-1'));

        $cache->invalidateReporter('u-1');
        $this->assertSame(2, Cache::get('dashboard:version:reporter:u-1'));

        $cache->invalidateReporter('u-1');
        $this->assertSame(3, Cache::get('dashboard:version:reporter:u-1'));
    }

    // ── Feature: events → invalidation pipeline ───────────────────

    public function test_ticket_created_event_invalidates_reporter_dashboard_cache(): void
    {
        $reporter = $this->userWithRole('reporter');
        $cache = app(DashboardCache::class);
        $calls = 0;

        $cb = function () use (&$calls): array {
            $calls++;

            return ['chips' => [], 'tickets' => [], 'summary' => []];
        };

        // Prime cache
        $cache->rememberReporter($reporter->id, ['surface' => 'reporter_dashboard'], 60, $cb);
        $this->assertSame(1, $calls);

        // Dispatch TicketCreated for this reporter (sync listener fires immediately)
        $ticket = $this->ticketFor($reporter, 'open', 'Nuevo ticket');
        TicketCreated::dispatch($ticket);

        // Cache must have been invalidated → callback runs again
        $cache->rememberReporter($reporter->id, ['surface' => 'reporter_dashboard'], 60, $cb);
        $this->assertSame(2, $calls);
    }

    public function test_ticket_state_changed_event_invalidates_reporter_dashboard_cache(): void
    {
        $reporter = $this->userWithRole('reporter');
        $maintenance = $this->userWithRole('maintenance');
        $cache = app(DashboardCache::class);
        $calls = 0;

        $cb = function () use (&$calls): array {
            $calls++;

            return ['chips' => [], 'tickets' => [], 'summary' => []];
        };

        $ticket = $this->ticketFor($reporter, 'open', 'Ticket a cambiar');

        $cache->rememberReporter($reporter->id, ['surface' => 'reporter_dashboard'], 60, $cb);
        $this->assertSame(1, $calls);

        TicketStateChanged::dispatch($ticket, $maintenance, 'open', 'in_progress');

        $cache->rememberReporter($reporter->id, ['surface' => 'reporter_dashboard'], 60, $cb);
        $this->assertSame(2, $calls);
    }

    public function test_ticket_created_for_other_reporter_does_not_invalidate_my_cache(): void
    {
        $me = $this->userWithRole('reporter');
        $other = $this->userWithRole('reporter');
        $cache = app(DashboardCache::class);
        $myCalls = 0;

        $myCallback = function () use (&$myCalls): array {
            $myCalls++;

            return ['chips' => [], 'tickets' => [], 'summary' => []];
        };

        $cache->rememberReporter($me->id, ['surface' => 'reporter_dashboard'], 60, $myCallback);
        $this->assertSame(1, $myCalls);

        // Another reporter creates a ticket → should only invalidate THEIR cache
        $otherTicket = $this->ticketFor($other, 'open', 'Ticket de otro');
        TicketCreated::dispatch($otherTicket);

        // My cache should still be valid
        $cache->rememberReporter($me->id, ['surface' => 'reporter_dashboard'], 60, $myCallback);
        $this->assertSame(1, $myCalls, 'Cache for user A must not be invalidated by events for user B');
    }

    public function test_marking_notifications_as_read_does_not_invalidate_dashboard_cache(): void
    {
        $reporter = $this->userWithRole('reporter');
        $cache = app(DashboardCache::class);
        $calls = 0;

        $cb = function () use (&$calls): array {
            $calls++;

            return ['chips' => [], 'tickets' => [], 'summary' => []];
        };

        // Prime the dashboard cache
        $cache->rememberReporter($reporter->id, ['surface' => 'reporter_dashboard'], 60, $cb);
        $this->assertSame(1, $calls);

        // Record version before marking notifications
        $versionBefore = Cache::get("dashboard:version:reporter:{$reporter->id}");

        // Mark all notifications as read
        $this->actingAs($reporter)->post(route('notifications.readAll'));

        // Dashboard version must be unchanged
        $this->assertSame(
            $versionBefore,
            Cache::get("dashboard:version:reporter:{$reporter->id}"),
            'Marking notifications as read must not bump the dashboard version key'
        );

        // Dashboard callback must not be called again
        $cache->rememberReporter($reporter->id, ['surface' => 'reporter_dashboard'], 60, $cb);
        $this->assertSame(1, $calls, 'Dashboard cache must not be invalidated by notification events');
    }

    // ── Feature: full HTTP route ──────────────────────────────────

    public function test_reporter_dashboard_route_is_ok_and_shows_own_ticket(): void
    {
        $reporter = $this->userWithRole('reporter');
        $this->ticketFor($reporter, 'open', 'Mi ticket en dashboard');

        $this->actingAs($reporter)
            ->get(route('reporter.dashboard'))
            ->assertOk()
            ->assertSeeText('Mi ticket en dashboard');
    }

    public function test_reporter_dashboard_route_does_not_expose_other_reporters_tickets(): void
    {
        $me = $this->userWithRole('reporter');
        $other = $this->userWithRole('reporter');

        $this->ticketFor($me, 'open', 'Mi ticket propio');
        $this->ticketFor($other, 'open', 'Ticket ajeno que no debe verse');

        $response = $this->actingAs($me)->get(route('reporter.dashboard'));

        $response->assertOk();
        $response->assertSeeText('Mi ticket propio');
        $response->assertDontSeeText('Ticket ajeno que no debe verse');
    }

    public function test_second_dashboard_request_returns_cached_data(): void
    {
        $reporter = $this->userWithRole('reporter');
        $this->ticketFor($reporter, 'open', 'Ticket en cache');

        // First request — cache miss, populates version key + data
        $this->actingAs($reporter)->get(route('reporter.dashboard'))->assertOk();

        $versionAfterFirst = Cache::get("dashboard:version:reporter:{$reporter->id}");

        // Second request — cache hit, version key must not change
        $this->actingAs($reporter)->get(route('reporter.dashboard'))->assertOk()->assertSeeText('Ticket en cache');

        $this->assertSame(
            $versionAfterFirst,
            Cache::get("dashboard:version:reporter:{$reporter->id}"),
            'Version key must not change between cache-hit requests'
        );
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function userWithRole(string $role): User
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $r) {
            Role::findOrCreate($r, 'web');
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function ticketFor(User $reporter, string $state, string $title, array $attrs = []): Ticket
    {
        return Ticket::create([
            'title' => $title,
            'description' => 'Descripción de '.$title,
            'reporter_id' => $reporter->id,
            'location_id' => $attrs['location_id'] ?? $this->location()->id,
            'category_id' => $attrs['category_id'] ?? $this->category()->id,
            'state' => $state,
            'priority' => $attrs['priority'] ?? 'medium',
            'assignment_locked' => false,
        ]);
    }

    private function location(): Location
    {
        return Location::firstOrCreate(
            ['room_code' => 'CACHE-TEST-LAB'],
            [
                'name' => 'Cache Test Lab',
                'building' => 'Edificio Test',
                'floor' => '1',
                'qr_token' => 'qr-'.Str::uuid()->toString(),
                'is_active' => true,
            ],
        );
    }

    private function category(): Category
    {
        return Category::firstOrCreate(
            ['name' => 'Cache Test Category'],
            ['description' => 'Category for cache tests'],
        );
    }
}
