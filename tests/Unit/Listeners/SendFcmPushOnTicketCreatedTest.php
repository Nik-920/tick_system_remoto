<?php

namespace Tests\Unit\Listeners;

use App\Contracts\Notifications\PushNotificationProvider;
use App\Events\TicketCreated;
use App\Listeners\SendFcmPushOnTicketCreated;
use App\Models\Ticket;
use App\Models\TicketNotificationPreference;
use App\Models\User;
use App\Services\Notifications\TicketNotificationPreferenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Tests\Fakes\FakePushNotificationProvider;
use Tests\TestCase;

/**
 * Listener push FCM de TicketCreated — per-user con preferencias.
 * Responsabilidad única: enviar FCM a admins/super_admins respetando opt-out.
 */
class SendFcmPushOnTicketCreatedTest extends TestCase
{
    use RefreshDatabase;

    private FakePushNotificationProvider $fcm;

    private TicketNotificationPreferenceService $prefs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureRolesExist();
        $this->fcm = new FakePushNotificationProvider;
        $this->prefs = app(TicketNotificationPreferenceService::class);
    }

    // ── Envío por defecto ────────────────────────────────────────────────────

    public function test_sends_fcm_to_admin_when_fcm_enabled_by_default(): void
    {
        Log::spy();

        $admin = $this->makeAdmin();

        $this->makeListener()->handle(new TicketCreated($this->makeTicket(), 'corr-1'));

        $this->fcm->assertSentToUser($admin->id);
    }

    public function test_sends_fcm_to_super_admin_when_fcm_enabled_by_default(): void
    {
        Log::spy();

        $superAdmin = $this->makeSuperAdmin();

        $this->makeListener()->handle(new TicketCreated($this->makeTicket(), 'corr-2'));

        $this->fcm->assertSentToUser($superAdmin->id);
    }

    public function test_does_not_send_fcm_to_reporter(): void
    {
        Log::spy();

        $reporter = $this->makeUser('reporter');

        $this->makeListener()->handle(new TicketCreated($this->makeTicket(), 'corr-3'));

        $this->fcm->assertNotSentToUser($reporter->id);
    }

    public function test_does_not_send_fcm_to_maintenance(): void
    {
        Log::spy();

        $maintenance = $this->makeUser('maintenance');

        $this->makeListener()->handle(new TicketCreated($this->makeTicket(), 'corr-4'));

        $this->fcm->assertNotSentToUser($maintenance->id);
    }

    // ── Opt-out por preferencia ──────────────────────────────────────────────

    public function test_admin_opt_out_fcm_does_not_receive_push(): void
    {
        Log::spy();

        $admin = $this->makeAdmin();
        $this->prefs->set($admin, TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN, TicketNotificationPreference::CHANNEL_FCM, false);

        $this->makeListenerWithPrefs()->handle(new TicketCreated($this->makeTicket(), 'corr-5'));

        $this->fcm->assertNotSentToUser($admin->id);
    }

    public function test_super_admin_opt_out_fcm_does_not_receive_push(): void
    {
        Log::spy();

        $superAdmin = $this->makeSuperAdmin();
        $this->prefs->set($superAdmin, TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN, TicketNotificationPreference::CHANNEL_FCM, false);

        $this->makeListenerWithPrefs()->handle(new TicketCreated($this->makeTicket(), 'corr-6'));

        $this->fcm->assertNotSentToUser($superAdmin->id);
    }

    public function test_admin_opt_out_in_app_but_fcm_enabled_still_receives_fcm(): void
    {
        Log::spy();

        $admin = $this->makeAdmin();
        $this->prefs->set($admin, TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN, TicketNotificationPreference::CHANNEL_IN_APP, false);

        $this->makeListenerWithPrefs()->handle(new TicketCreated($this->makeTicket(), 'corr-7'));

        $this->fcm->assertSentToUser($admin->id);
    }

    public function test_opt_out_of_one_admin_does_not_affect_other_admin(): void
    {
        Log::spy();

        $adminA = $this->makeAdmin();
        $adminB = $this->makeAdmin();
        $this->prefs->set($adminA, TicketNotificationPreference::TYPE_TICKET_CREATED_ADMIN, TicketNotificationPreference::CHANNEL_FCM, false);

        $this->makeListenerWithPrefs()->handle(new TicketCreated($this->makeTicket(), 'corr-8'));

        $this->fcm->assertNotSentToUser($adminA->id);
        $this->fcm->assertSentToUser($adminB->id);
    }

    // ── Deduplicación ────────────────────────────────────────────────────────

    public function test_user_with_admin_and_super_admin_roles_receives_push_only_once(): void
    {
        Log::spy();

        $user = User::factory()->create();
        $user->assignRole('admin');
        $user->assignRole('super_admin');

        $this->makeListener()->handle(new TicketCreated($this->makeTicket(), 'corr-9'));

        $sentIds = array_column(array_column($this->fcm->sentToUsers, 'user'), 'id');
        $this->assertCount(1, array_filter($sentIds, fn ($id) => $id === $user->id));
    }

    // ── Payload ──────────────────────────────────────────────────────────────

    public function test_push_body_contains_location_and_category(): void
    {
        Log::spy();

        $this->makeAdmin();

        $ticket = $this->makeTicket(
            title: 'Fuga de agua',
            locationName: 'Edificio A',
            categoryName: 'Infraestructura',
        );

        $this->makeListener()->handle(new TicketCreated($ticket));

        $body = $this->fcm->sentToUsers[0]['body'] ?? '';
        $this->assertStringContainsString('Fuga de agua', $body);
        $this->assertStringContainsString('Edificio A', $body);
        $this->assertStringContainsString('Infraestructura', $body);
    }

    public function test_push_data_contains_ticket_id_and_type(): void
    {
        Log::spy();

        $this->makeAdmin();

        $this->makeListener()->handle(new TicketCreated($this->makeTicket()));

        $data = $this->fcm->sentToUsers[0]['data'] ?? [];
        $this->assertSame('ticket-uuid-001', $data['ticket_id'] ?? null);
        $this->assertSame('ticket_created', $data['type'] ?? null);
    }

    public function test_admin_without_fcm_tokens_does_not_cause_exception(): void
    {
        Log::spy();

        $this->makeAdmin();

        $threw = false;
        try {
            $this->makeListener()->handle(new TicketCreated($this->makeTicket(), 'corr-10'));
        } catch (\Throwable) {
            $threw = true;
        }

        $this->assertFalse($threw);
    }

    // ── Manejo de errores ────────────────────────────────────────────────────

    public function test_exception_inside_handler_is_caught_and_logged(): void
    {
        Log::spy();

        $this->makeAdmin();

        $fcm = $this->createMock(PushNotificationProvider::class);
        $fcm->method('sendToUser')
            ->willThrowException(new \RuntimeException('FCM exploded'));

        (new SendFcmPushOnTicketCreated($fcm))->handle(new TicketCreated($this->makeTicket()));

        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('error')
            ->withArgs(fn ($msg) => str_contains((string) $msg, 'push FCM'));

        $this->addToAssertionCount(1);
    }

    // ── Sin admins en DB ─────────────────────────────────────────────────────

    public function test_no_admins_in_db_sends_nothing(): void
    {
        Log::spy();

        $this->makeUser('reporter');

        $this->makeListener()->handle(new TicketCreated($this->makeTicket(), 'corr-11'));

        $this->fcm->assertNothingSent();
    }

    // ── Queue contract ───────────────────────────────────────────────────────

    public function test_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, $this->makeListener());
    }

    public function test_uses_notifications_queue(): void
    {
        $this->assertSame('notifications', $this->makeListener()->queue);
    }

    public function test_has_max_tries_of_one(): void
    {
        $this->assertSame(1, $this->makeListener()->tries);
    }

    public function test_after_commit_is_true(): void
    {
        $this->assertTrue($this->makeListener()->afterCommit);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeListener(): SendFcmPushOnTicketCreated
    {
        return new SendFcmPushOnTicketCreated($this->fcm);
    }

    private function makeListenerWithPrefs(): SendFcmPushOnTicketCreated
    {
        return new SendFcmPushOnTicketCreated($this->fcm, $this->prefs);
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

    private function makeTicket(
        string $title = 'Ticket de prueba',
        ?string $locationName = 'Sala de reuniones',
        ?string $categoryName = 'Eléctrico',
    ): Ticket {
        $ticket = new Ticket;
        $ticket->id = 'ticket-uuid-001';
        $ticket->title = $title;

        $ticket->setRelation('location', $locationName !== null ? (object) ['name' => $locationName] : null);
        $ticket->setRelation('category', $categoryName !== null ? (object) ['name' => $categoryName] : null);

        return $ticket;
    }
}
