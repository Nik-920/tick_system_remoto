<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use App\Support\LocalTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Guards that all ticket-related dates are displayed in America/Lima timezone.
 *
 * Root cause fixed: dates were stored as UTC but several query classes called
 * ->format() directly (no timezone conversion), and the .env had a conflicting
 * duplicate APP_TIMEZONE=UTC entry. This test suite prevents both regressions.
 *
 * DB fixture: PostgreSQL preserves timestamp with timezone.
 * The canonical instant used across tests is 2026-06-22 03:02:00 UTC
 * which equals 2026-06-21 22:02:00 America/Lima (UTC-5).
 */
class PeruTimezoneDisplayTest extends TestCase
{
    use RefreshDatabase;

    /** UTC instant: 2026-06-22 03:02:00 +00 = 2026-06-21 22:02 Lima. */
    private const INSTANT_UTC = '2026-06-22 03:02:00';

    /** Expected Lima display: the date is June 21, not June 22. */
    private const EXPECTED_DATE = '21/06/2026 22:02';

    /** The "off by 5h" display that the bug produced. */
    private const BUG_DATE = '22/06/2026 03:02';

    /** A second instant 3 minutes later (used for updated_at). */
    private const INSTANT_UTC_UPDATED = '2026-06-22 03:05:00';

    private const EXPECTED_DATE_UPDATED = '21/06/2026 22:05';

    // ── Unit: LocalTime::format() ──────────────────────────────────────────────

    public function test_local_time_format_converts_utc_carbon_to_lima(): void
    {
        $utc = Carbon::create(2026, 6, 22, 3, 2, 0, 'UTC');

        $result = LocalTime::format($utc);

        $this->assertSame(self::EXPECTED_DATE, $result);
        $this->assertNotSame(self::BUG_DATE, $result);
    }

    public function test_local_time_format_returns_null_for_null_input(): void
    {
        $this->assertNull(LocalTime::format(null));
    }

    public function test_local_time_format_accepts_custom_format(): void
    {
        $utc = Carbon::create(2026, 6, 22, 3, 2, 0, 'UTC');

        $this->assertSame('21/06/2026', LocalTime::format($utc, 'd/m/Y'));
        $this->assertSame('22:02', LocalTime::format($utc, 'H:i'));
    }

    public function test_local_time_display_timezone_is_america_lima(): void
    {
        $this->assertSame('America/Lima', LocalTime::displayTimezone());
    }

    public function test_local_time_no_double_conversion_when_already_lima(): void
    {
        $lima = Carbon::create(2026, 6, 21, 22, 2, 0, 'America/Lima');

        $result = LocalTime::format($lima);

        $this->assertSame(self::EXPECTED_DATE, $result);
    }

    // ── Feature: /tickets/{ticket} show page ──────────────────────────────────

    public function test_ticket_show_displays_created_at_in_lima_time(): void
    {
        $reporter = $this->reporter();
        $ticket = $this->ticketAt($reporter, self::INSTANT_UTC, self::INSTANT_UTC);

        $response = $this->actingAs($reporter)->get(route('tickets.show', $ticket));

        $response->assertOk();
        $response->assertSee(self::EXPECTED_DATE, false);
        $response->assertDontSee(self::BUG_DATE, false);
    }

    public function test_ticket_show_displays_updated_at_in_lima_time(): void
    {
        $reporter = $this->reporter();
        $ticket = $this->ticketAt($reporter, self::INSTANT_UTC, self::INSTANT_UTC_UPDATED);

        $response = $this->actingAs($reporter)->get(route('tickets.show', $ticket));

        $response->assertOk();
        $response->assertSee(self::EXPECTED_DATE_UPDATED, false);
    }

    public function test_ticket_show_does_not_display_raw_utc_date(): void
    {
        $reporter = $this->reporter();
        $ticket = $this->ticketAt($reporter, self::INSTANT_UTC, self::INSTANT_UTC);

        $response = $this->actingAs($reporter)->get(route('tickets.show', $ticket));

        $response->assertOk();
        $response->assertDontSee(self::BUG_DATE, false);
    }

    // ── Feature: /reporter/tickets/{ticket} tracking page ────────────────────

    public function test_reporter_tracking_displays_reportado_step_in_lima_time(): void
    {
        $reporter = $this->reporter();
        $ticket = $this->ticketAt($reporter, self::INSTANT_UTC, self::INSTANT_UTC);

        $response = $this->actingAs($reporter)->get(route('reporter.tickets.show', $ticket));

        $response->assertOk();
        // The "Reportado" step and details row use LocalTime::format() now.
        $response->assertSee('21/06/2026', false);
        $response->assertDontSee('22/06/2026 03', false);
    }

    public function test_reporter_tracking_details_updated_at_in_lima_time(): void
    {
        $reporter = $this->reporter();
        $ticket = $this->ticketAt($reporter, self::INSTANT_UTC, self::INSTANT_UTC_UPDATED);

        $response = $this->actingAs($reporter)->get(route('reporter.tickets.show', $ticket));

        $response->assertOk();
        // Details row "Última actualización" should show Lima date.
        $response->assertSee('21/06/2026', false);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function reporter(): User
    {
        Role::firstOrCreate(['name' => 'reporter', 'guard_name' => 'web']);

        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole('reporter');

        return $user;
    }

    private function ticketAt(User $reporter, string $createdAtUtc, string $updatedAtUtc): Ticket
    {
        $ticket = Ticket::create([
            'title' => 'Ticket Test Timezone',
            'description' => 'Verificación de timezone Lima',
            'reporter_id' => $reporter->id,
            'location_id' => $this->location()->id,
            'category_id' => $this->category()->id,
            'state' => 'open',
            'priority' => 'medium',
            'assignment_locked' => false,
        ]);

        // Force the UTC timestamps directly; Carbon preserves the +00 offset so
        // PostgreSQL stores them as true UTC and LocalTime can convert them.
        $ticket->timestamps = false;
        $ticket->created_at = Carbon::parse($createdAtUtc, 'UTC');
        $ticket->updated_at = Carbon::parse($updatedAtUtc, 'UTC');
        $ticket->save();
        $ticket->timestamps = true;

        return $ticket->fresh();
    }

    private function location(): Location
    {
        return Location::firstOrCreate(
            ['room_code' => 'TZ-TEST-01'],
            [
                'name' => 'Laboratorio Timezone Test',
                'building' => 'Edificio Test',
                'floor' => '1',
                'qr_token' => 'qr-tz-test-'.Str::uuid(),
                'is_active' => true,
            ],
        );
    }

    private function category(): Category
    {
        return Category::firstOrCreate(
            ['name' => 'TimezoneTest'],
            ['icon' => 'clock', 'description' => 'Categoría para tests de timezone'],
        );
    }
}
