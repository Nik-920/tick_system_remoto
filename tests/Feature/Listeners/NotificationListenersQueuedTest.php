<?php

namespace Tests\Feature\Listeners;

use App\Events\TicketAssigned;
use App\Events\TicketCreated;
use App\Events\TicketStateChanged;
use App\Listeners\CreateInAppNotificationOnTicketAssigned;
use App\Listeners\CreateInAppNotificationOnTicketCreated;
use App\Listeners\CreateInAppNotificationOnTicketStateChanged;
use App\Listeners\SendFcmPushOnTicketAssigned;
use App\Listeners\SendFcmPushOnTicketCreated;
use App\Listeners\SendFcmPushOnTicketStateChanged;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Fase 3 (punto 3.4) + Fase 4.
 *
 * Verifica que, al separar los listeners de notificación (SRP), cada evento
 * encola DOS listeners con semánticas de queue distintas:
 *
 *   - CreateInAppNotificationOn*  → queue 'default'        (escritura in-app, reintentable)
 *   - SendFcmPushOn*              → queue 'notifications'   (push FCM, 1 intento)
 *
 * Ninguno se ejecuta sincrónicamente: con Queue::fake() quedan capturados
 * como CallQueuedListener en lugar de correr durante el request.
 */
class NotificationListenersQueuedTest extends TestCase
{
    // ──────────────────────────────────────────────────────────
    // TicketStateChanged → in-app (default) + push (notifications)
    // ──────────────────────────────────────────────────────────

    public function test_state_changed_queues_in_app_on_default_and_push_on_notifications(): void
    {
        Queue::fake();

        $reporter = $this->fakeUser('reporter-q1');
        $actor    = $this->fakeUser('actor-q1');
        $ticket   = $this->fakeTicket('ticket-q1', $reporter);

        event(new TicketStateChanged($ticket, $actor, 'open', 'resolved', 'corr-q1'));

        $this->assertQueuedOn('default', CreateInAppNotificationOnTicketStateChanged::class);
        $this->assertQueuedOn('notifications', SendFcmPushOnTicketStateChanged::class);
    }

    public function test_state_changed_listeners_are_not_executed_synchronously(): void
    {
        Queue::fake();

        $reporter = $this->fakeUser('reporter-q2');
        $actor    = $this->fakeUser('actor-q2');
        $ticket   = $this->fakeTicket('ticket-q2', $reporter);

        event(new TicketStateChanged($ticket, $actor, 'open', 'in_progress', 'corr-q2'));

        // Solo se encolan como CallQueuedListener, nunca como job propio.
        Queue::assertNotPushed(CreateInAppNotificationOnTicketStateChanged::class);
        Queue::assertNotPushed(SendFcmPushOnTicketStateChanged::class);
    }

    // ──────────────────────────────────────────────────────────
    // TicketAssigned → in-app (default) + push (notifications)
    // ──────────────────────────────────────────────────────────

    public function test_assigned_queues_in_app_on_default_and_push_on_notifications(): void
    {
        Queue::fake();

        $actor    = $this->fakeUser('actor-q3');
        $assignee = $this->fakeUser('assignee-q3');
        $ticket   = $this->fakeTicket('ticket-q3');

        event(new TicketAssigned($ticket, $actor, null, $assignee, 'assigned', 'corr-q3'));

        $this->assertQueuedOn('default', CreateInAppNotificationOnTicketAssigned::class);
        $this->assertQueuedOn('notifications', SendFcmPushOnTicketAssigned::class);
    }

    // ──────────────────────────────────────────────────────────
    // TicketCreated → in-app (default) + push (notifications)
    //
    // AI desactivado para que los listeners de embedding/dedup hagan
    // early return y no añadan ruido.
    // ──────────────────────────────────────────────────────────

    public function test_created_queues_in_app_on_default_and_push_on_notifications(): void
    {
        config(['ai.enabled' => false]);

        Queue::fake();

        $ticket = $this->fakeTicket('ticket-q4');

        event(new TicketCreated($ticket, 'corr-q4'));

        $this->assertQueuedOn('default', CreateInAppNotificationOnTicketCreated::class);
        $this->assertQueuedOn('notifications', SendFcmPushOnTicketCreated::class);
    }

    // ──────────────────────────────────────────────────────────
    // Los tres push FCM van a 'notifications'; los tres in-app a 'default'
    // ──────────────────────────────────────────────────────────

    public function test_all_push_listeners_go_to_notifications_and_in_app_to_default(): void
    {
        config(['ai.enabled' => false]);

        Queue::fake();

        $reporter = $this->fakeUser('reporter-q5');
        $actor    = $this->fakeUser('actor-q5');
        $assignee = $this->fakeUser('assignee-q5');
        $ticket   = $this->fakeTicket('ticket-q5', $reporter);

        event(new TicketStateChanged($ticket, $actor, 'open', 'resolved', 'corr-q5a'));
        event(new TicketAssigned($ticket, $actor, null, $assignee, 'assigned', 'corr-q5b'));
        event(new TicketCreated($ticket, 'corr-q5c'));

        // Push FCM → 'notifications'
        $this->assertQueuedOn('notifications', SendFcmPushOnTicketStateChanged::class);
        $this->assertQueuedOn('notifications', SendFcmPushOnTicketAssigned::class);
        $this->assertQueuedOn('notifications', SendFcmPushOnTicketCreated::class);

        // In-app → 'default'
        $this->assertQueuedOn('default', CreateInAppNotificationOnTicketStateChanged::class);
        $this->assertQueuedOn('default', CreateInAppNotificationOnTicketAssigned::class);
        $this->assertQueuedOn('default', CreateInAppNotificationOnTicketCreated::class);
    }

    // ──────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────

    /**
     * @param  class-string  $listenerClass
     */
    private function assertQueuedOn(string $queue, string $listenerClass): void
    {
        Queue::assertPushedOn(
            $queue,
            CallQueuedListener::class,
            fn (CallQueuedListener $job): bool => $job->class === $listenerClass
        );
    }

    private function fakeUser(string $id): User
    {
        $user     = new User;
        $user->id = $id;

        return $user;
    }

    private function fakeTicket(string $id, ?User $reporter = null): Ticket
    {
        $ticket        = new Ticket;
        $ticket->id    = $id;
        $ticket->title = 'Ticket de prueba queue';
        $ticket->setRelation('reporter', $reporter);
        $ticket->setRelation('location', null);
        $ticket->setRelation('category', null);

        return $ticket;
    }
}
