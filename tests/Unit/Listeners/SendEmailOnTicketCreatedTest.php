<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\TicketCreated;
use App\Listeners\SendEmailOnTicketCreated;
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

class SendEmailOnTicketCreatedTest extends TestCase
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

    public function test_sends_email_to_admin_when_enabled_by_default(): void
    {
        Log::spy();
        $admin = $this->makeAdmin();

        $this->makeListener()->handle(new TicketCreated($this->makeTicket(), 'corr-1'));

        $this->provider->assertSentTo($admin->email);
    }

    public function test_sends_email_to_super_admin(): void
    {
        Log::spy();
        $superAdmin = $this->makeSuperAdmin();

        $this->makeListener()->handle(new TicketCreated($this->makeTicket(), 'corr-2'));

        $this->provider->assertSentTo($superAdmin->email);
    }

    public function test_does_not_send_to_reporter(): void
    {
        Log::spy();
        $reporter = $this->makeUser('reporter');

        $this->makeListener()->handle(new TicketCreated($this->makeTicket(), 'corr-3'));

        $this->provider->assertNotSentTo($reporter->email);
    }

    public function test_admin_opt_out_email_does_not_receive(): void
    {
        Log::spy();
        $admin = $this->makeAdmin();
        $this->prefs->set($admin, TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN, TicketNotificationPreference::CHANNEL_EMAIL, false);

        $this->makeListener()->handle(new TicketCreated($this->makeTicket(), 'corr-4'));

        $this->provider->assertNotSentTo($admin->email);
    }

    public function test_opt_out_of_one_admin_does_not_affect_other(): void
    {
        Log::spy();
        $adminA = $this->makeAdmin();
        $adminB = $this->makeAdmin();
        $this->prefs->set($adminA, TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN, TicketNotificationPreference::CHANNEL_EMAIL, false);

        $this->makeListener()->handle(new TicketCreated($this->makeTicket(), 'corr-5'));

        $this->provider->assertNotSentTo($adminA->email);
        $this->provider->assertSentTo($adminB->email);
    }

    public function test_admin_without_email_does_not_cause_exception(): void
    {
        Log::spy();
        $admin = $this->makeAdmin();
        $admin->forceFill(['email' => ''])->save();

        $threw = false;
        try {
            $this->makeListener()->handle(new TicketCreated($this->makeTicket(), 'corr-6'));
        } catch (\Throwable) {
            $threw = true;
        }

        $this->assertFalse($threw);
    }

    public function test_no_admins_in_db_sends_nothing(): void
    {
        Log::spy();
        $this->makeUser('reporter');

        $this->makeListener()->handle(new TicketCreated($this->makeTicket(), 'corr-7'));

        $this->provider->assertNothingSent();
    }

    public function test_exception_inside_handler_is_caught_and_logged(): void
    {
        Log::spy();
        $this->makeAdmin();

        $throwingService = $this->createMock(EmailNotificationService::class);
        $throwingService->method('sendTicketEmail')->willThrowException(new \RuntimeException('boom'));

        (new SendEmailOnTicketCreated($throwingService))->handle(new TicketCreated($this->makeTicket()));

        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('error')->withArgs(fn ($msg) => str_contains((string) $msg, 'ticket creado'));
        $this->addToAssertionCount(1);
    }

    public function test_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, $this->makeListener());
    }

    public function test_uses_notifications_queue(): void
    {
        $this->assertSame('notifications', $this->makeListener()->queue);
    }

    public function test_has_max_tries_of_three(): void
    {
        $this->assertSame(3, $this->makeListener()->tries);
    }

    public function test_after_commit_is_true(): void
    {
        $this->assertTrue($this->makeListener()->afterCommit);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeListener(): SendEmailOnTicketCreated
    {
        return new SendEmailOnTicketCreated(new EmailNotificationService($this->provider, $this->prefs));
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function makeSuperAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user;
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

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
        $location = Location::create([
            'name' => 'Sala email-created '.uniqid(),
            'building' => 'Edificio C',
            'floor' => '1',
            'room_code' => 'C-'.uniqid(),
            'qr_token' => 'qr-c-'.uniqid(),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Cat email-created '.uniqid(),
            'icon' => 'bolt',
            'description' => 'Test email created',
        ]);

        return Ticket::create([
            'title' => 'Ticket de prueba',
            'description' => 'Descripcion de prueba.',
            'reporter_id' => $this->makeUser('reporter')->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
    }
}
