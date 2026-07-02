<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\TicketAssigned;
use App\Listeners\SendEmailOnTicketAssigned;
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

class SendEmailOnTicketAssignedTest extends TestCase
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

    public function test_assigned_action_sends_email_to_new_assignee(): void
    {
        Log::spy();
        $admin = $this->makeAdmin();
        $maintenance = $this->makeMaintenance();
        $ticket = $this->makeTicket();

        $this->makeListener()->handle(new TicketAssigned($ticket, $admin, null, $maintenance, 'assigned', 'corr-1'));

        $this->provider->assertSentTo($maintenance->email);
    }

    public function test_unassigned_action_sends_email_to_previous_assignee(): void
    {
        Log::spy();
        $admin = $this->makeAdmin();
        $maintenance = $this->makeMaintenance();
        $ticket = $this->makeTicket();

        $this->makeListener()->handle(new TicketAssigned($ticket, $admin, $maintenance, null, 'unassigned', 'corr-2'));

        $this->provider->assertSentTo($maintenance->email);
    }

    public function test_claimed_action_sends_nothing(): void
    {
        Log::spy();
        $admin = $this->makeAdmin();
        $maintenance = $this->makeMaintenance();
        $ticket = $this->makeTicket();

        $this->makeListener()->handle(new TicketAssigned($ticket, $admin, null, $maintenance, 'claimed', 'corr-3'));

        $this->provider->assertNothingSent();
    }

    public function test_released_action_sends_nothing(): void
    {
        Log::spy();
        $admin = $this->makeAdmin();
        $maintenance = $this->makeMaintenance();
        $ticket = $this->makeTicket();

        $this->makeListener()->handle(new TicketAssigned($ticket, $admin, $maintenance, null, 'released', 'corr-4'));

        $this->provider->assertNothingSent();
    }

    public function test_assignee_opt_out_assigned_email_prevents_send(): void
    {
        Log::spy();
        $admin = $this->makeAdmin();
        $maintenance = $this->makeMaintenance();
        $ticket = $this->makeTicket();
        $this->prefs->set($maintenance, TicketNotificationPreference::TYPE_TICKET_ASSIGNED_ASSIGNEE, TicketNotificationPreference::CHANNEL_EMAIL, false);

        $this->makeListener()->handle(new TicketAssigned($ticket, $admin, null, $maintenance, 'assigned', 'corr-5'));

        $this->provider->assertNothingSent();
    }

    public function test_assignee_opt_out_unassigned_email_prevents_send(): void
    {
        Log::spy();
        $admin = $this->makeAdmin();
        $maintenance = $this->makeMaintenance();
        $ticket = $this->makeTicket();
        $this->prefs->set($maintenance, TicketNotificationPreference::TYPE_TICKET_UNASSIGNED_ASSIGNEE, TicketNotificationPreference::CHANNEL_EMAIL, false);

        $this->makeListener()->handle(new TicketAssigned($ticket, $admin, $maintenance, null, 'unassigned', 'corr-6'));

        $this->provider->assertNothingSent();
    }

    public function test_exception_inside_handler_is_caught_and_logged(): void
    {
        Log::spy();
        $admin = $this->makeAdmin();
        $maintenance = $this->makeMaintenance();

        $throwingService = $this->createMock(EmailNotificationService::class);
        $throwingService->method('sendTicketEmail')->willThrowException(new \RuntimeException('boom'));

        (new SendEmailOnTicketAssigned($throwingService))->handle(new TicketAssigned($this->makeTicket(), $admin, null, $maintenance, 'assigned'));

        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('error')->withArgs(fn ($msg) => str_contains((string) $msg, 'asignación'));
        $this->addToAssertionCount(1);
    }

    public function test_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, $this->makeListener());
    }

    public function test_uses_notifications_queue_with_three_tries(): void
    {
        $listener = $this->makeListener();
        $this->assertSame('notifications', $listener->queue);
        $this->assertSame(3, $listener->tries);
        $this->assertTrue($listener->afterCommit);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeListener(): SendEmailOnTicketAssigned
    {
        return new SendEmailOnTicketAssigned(new EmailNotificationService($this->provider, $this->prefs));
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

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

    private function makeTicket(): Ticket
    {
        $reporter = User::factory()->create();
        $reporter->assignRole('reporter');

        $location = Location::create([
            'name' => 'Sala email-assigned '.uniqid(),
            'building' => 'Edificio A',
            'floor' => '1',
            'room_code' => 'A-'.uniqid(),
            'qr_token' => 'qr-a-'.uniqid(),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Cat email-assigned '.uniqid(),
            'icon' => 'bolt',
            'description' => 'Test email assigned',
        ]);

        return Ticket::create([
            'title' => 'Ticket asignación prueba',
            'description' => 'Descripcion asignación.',
            'reporter_id' => $reporter->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
    }
}
