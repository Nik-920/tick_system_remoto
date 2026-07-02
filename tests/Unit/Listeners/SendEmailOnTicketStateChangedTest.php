<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\TicketStateChanged;
use App\Listeners\SendEmailOnTicketStateChanged;
use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\TicketNotificationPreference;
use App\Models\User;
use App\Services\Notifications\EmailNotificationService;
use App\Services\Notifications\TicketNotificationPreferenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Tests\Fakes\FakeEmailNotificationProvider;
use Tests\TestCase;

class SendEmailOnTicketStateChangedTest extends TestCase
{
    use RefreshDatabase;

    private FakeEmailNotificationProvider $provider;

    private TicketNotificationPreferenceService $prefs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureRolesExist();
        $this->provider = new FakeEmailNotificationProvider;
        $this->prefs = app(TicketNotificationPreferenceService::class);
    }

    public function test_reporter_receives_email_on_state_change(): void
    {
        Log::spy();
        $admin = $this->makeAdmin();
        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);

        $this->makeListener()->handle(new TicketStateChanged($ticket, $admin, 'open', 'resolved', 'corr-1'));

        $this->provider->assertSentTo($reporter->email);
    }

    public function test_reporter_self_notification_excluded(): void
    {
        Log::spy();
        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);

        $this->makeListener()->handle(new TicketStateChanged($ticket, $reporter, 'open', 'resolved', 'corr-2'));

        $this->provider->assertNotSentTo($reporter->email);
    }

    public function test_assignee_receives_email_on_state_change(): void
    {
        Log::spy();
        $admin = $this->makeAdmin();
        $reporter = $this->makeReporter();
        $maintenance = $this->makeMaintenance();
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);

        $this->makeListener()->handle(new TicketStateChanged($ticket, $admin, 'open', 'in_progress', 'corr-3'));

        $this->provider->assertSentTo($maintenance->email);
    }

    public function test_assignee_self_notification_excluded(): void
    {
        Log::spy();
        $reporter = $this->makeReporter();
        $maintenance = $this->makeMaintenance();
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);

        $this->makeListener()->handle(new TicketStateChanged($ticket, $maintenance, 'open', 'in_progress', 'corr-4'));

        $this->provider->assertNotSentTo($maintenance->email);
    }

    public function test_reporter_equals_assignee_does_not_duplicate_email(): void
    {
        Log::spy();
        $admin = $this->makeAdmin();
        $reporter = $this->makeReporter();
        $reporter->assignRole('maintenance');
        $ticket = $this->makeTicketWithAssignment($reporter, $reporter);

        $this->makeListener()->handle(new TicketStateChanged($ticket, $admin, 'open', 'in_progress', 'corr-5'));

        $this->assertCount(1, array_filter($this->provider->sent, fn ($s) => $s['to'] === $reporter->email));
    }

    public function test_assignee_without_maintenance_role_not_notified(): void
    {
        Log::spy();
        $admin = $this->makeAdmin();
        $reporter = $this->makeReporter();
        $notMaintenance = User::factory()->create();
        $ticket = $this->makeTicketWithAssignment($reporter, $notMaintenance);

        $this->makeListener()->handle(new TicketStateChanged($ticket, $admin, 'open', 'in_progress', 'corr-6'));

        $this->provider->assertNotSentTo($notMaintenance->email);
    }

    public function test_reporter_opt_out_email_prevents_send(): void
    {
        Log::spy();
        $admin = $this->makeAdmin();
        $reporter = $this->makeReporter();
        $ticket = $this->makeTicket($reporter);
        $this->prefs->set($reporter, TicketNotificationPreference::TYPE_TICKET_STATE_REPORTER, TicketNotificationPreference::CHANNEL_EMAIL, false);

        $this->makeListener()->handle(new TicketStateChanged($ticket, $admin, 'open', 'resolved', 'corr-7'));

        $this->provider->assertNotSentTo($reporter->email);
    }

    public function test_assignee_opt_out_email_prevents_send(): void
    {
        Log::spy();
        $admin = $this->makeAdmin();
        $reporter = $this->makeReporter();
        $maintenance = $this->makeMaintenance();
        $ticket = $this->makeTicketWithAssignment($reporter, $maintenance);
        $this->prefs->set($maintenance, TicketNotificationPreference::TYPE_TICKET_STATE_ASSIGNEE, TicketNotificationPreference::CHANNEL_EMAIL, false);

        $this->makeListener()->handle(new TicketStateChanged($ticket, $admin, 'open', 'in_progress', 'corr-8'));

        $this->provider->assertNotSentTo($maintenance->email);
        $this->provider->assertSentTo($reporter->email);
    }

    public function test_implements_should_queue_with_notifications_queue(): void
    {
        $listener = $this->makeListener();
        $this->assertInstanceOf(ShouldQueue::class, $listener);
        $this->assertSame('notifications', $listener->queue);
        $this->assertSame(3, $listener->tries);
        $this->assertTrue($listener->afterCommit);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeListener(): SendEmailOnTicketStateChanged
    {
        return new SendEmailOnTicketStateChanged(new EmailNotificationService($this->provider, $this->prefs));
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function makeReporter(): User
    {
        $user = User::factory()->create();
        $user->assignRole('reporter');

        return $user;
    }

    private function makeMaintenance(): User
    {
        $user = User::factory()->create();
        $user->assignRole('maintenance');

        return $user;
    }

    private function ensureRolesExist(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function makeTicket(User $reporter): Ticket
    {
        $location = Location::create([
            'name' => 'Sala email-sc '.uniqid(),
            'building' => 'Edificio SC',
            'floor' => '1',
            'room_code' => 'SC-'.uniqid(),
            'qr_token' => 'qr-sc-'.uniqid(),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Cat email-sc '.uniqid(),
            'icon' => 'bolt',
            'description' => 'Test email state change',
        ]);

        return Ticket::create([
            'title' => 'Ticket state-change prueba',
            'description' => 'Descripcion state-change.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
    }

    private function makeTicketWithAssignment(User $reporter, User $assignee): Ticket
    {
        $ticket = $this->makeTicket($reporter);
        $ticket->assigned_to = $assignee->id;
        $ticket->save();

        return $ticket->fresh(['reporter', 'assignee']) ?? $ticket;
    }
}
