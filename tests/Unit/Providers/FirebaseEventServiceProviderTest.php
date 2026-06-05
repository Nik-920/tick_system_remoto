<?php

namespace Tests\Unit\Providers;

use App\Events\DuplicateDetected;
use App\Events\TicketAssigned;
use App\Events\TicketCreated;
use App\Events\TicketResolved;
use App\Events\TicketStateChanged;
use App\Listeners\CreateInAppNotificationOnTicketAssigned;
use App\Listeners\CreateInAppNotificationOnTicketCreated;
use App\Listeners\CreateInAppNotificationOnTicketStateChanged;
use App\Listeners\DispatchDuplicateDetectionOnTicketCreated;
use App\Listeners\GenerateEmbeddingOnTicketCreated;
use App\Listeners\LogDuplicateDetectionAudit;
use App\Listeners\ReportFailedQueueJob;
use App\Listeners\SendFcmPushOnTicketAssigned;
use App\Listeners\SendFcmPushOnTicketCreated;
use App\Listeners\SendFcmPushOnTicketStateChanged;
use App\Listeners\UpdateRecurrenceOnTicketResolved;
use App\Providers\EventServiceProvider;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Fase 1 — punto 1.3 (actualización de Fase 0, punto 0.7)
 *
 * Verifica que:
 *   1. App\Providers\EventServiceProvider está registrado como único provider activo.
 *   2. Los 6 eventos de dominio tienen al menos un listener registrado.
 *   3. Los conteos de listeners corresponden al mapeo esperado (sin duplicados).
 *   4. shouldDiscoverEvents() = false garantiza que auto-discovery no interfiere.
 *   5. Todos los listeners pueden ser resueltos por el contenedor.
 */
class FirebaseEventServiceProviderTest extends TestCase
{
    // ──────────────────────────────────────────────────────────
    // 1. El provider activo es App\Providers\EventServiceProvider
    // ──────────────────────────────────────────────────────────

    public function test_app_providers_event_service_provider_is_registered_in_application(): void
    {
        $loaded = array_keys($this->app->getLoadedProviders());

        $this->assertContains(
            EventServiceProvider::class,
            $loaded,
            'App\Providers\EventServiceProvider debe estar registrado en bootstrap/providers.php'
        );
    }

    // ──────────────────────────────────────────────────────────
    // 2. bootstrap/providers.php contiene App\Providers\ESP
    //    y NO contiene App\Services\Firebase\EventServiceProvider
    // ──────────────────────────────────────────────────────────

    public function test_bootstrap_providers_file_contains_app_esp(): void
    {
        /** @var array<int, class-string> $providers */
        $providers = require base_path('bootstrap/providers.php');

        $this->assertContains(
            EventServiceProvider::class,
            $providers,
            'bootstrap/providers.php debe incluir App\Providers\EventServiceProvider'
        );

        // String literal: la clase fue eliminada en Fase 1, ya no se puede usar ::class.
        $this->assertNotContains(
            'App\Services\Firebase\EventServiceProvider',
            $providers,
            'bootstrap/providers.php NO debe incluir App\Services\Firebase\EventServiceProvider (eliminado en Fase 1)'
        );
    }

    // ──────────────────────────────────────────────────────────
    // 3. Todos los eventos de dominio tienen listeners registrados
    // ──────────────────────────────────────────────────────────

    public function test_ticket_created_has_listeners_registered(): void
    {
        $this->assertTrue(
            Event::hasListeners(TicketCreated::class),
            'TicketCreated debe tener listeners registrados'
        );
    }

    public function test_ticket_state_changed_has_listeners_registered(): void
    {
        $this->assertTrue(
            Event::hasListeners(TicketStateChanged::class),
            'TicketStateChanged debe tener listeners registrados'
        );
    }

    public function test_ticket_resolved_has_listeners_registered(): void
    {
        $this->assertTrue(
            Event::hasListeners(TicketResolved::class),
            'TicketResolved debe tener listeners registrados'
        );
    }

    public function test_ticket_assigned_has_listeners_registered(): void
    {
        $this->assertTrue(
            Event::hasListeners(TicketAssigned::class),
            'TicketAssigned debe tener listeners registrados'
        );
    }

    public function test_duplicate_detected_has_listeners_registered(): void
    {
        $this->assertTrue(
            Event::hasListeners(DuplicateDetected::class),
            'DuplicateDetected debe tener listeners registrados'
        );
    }

    public function test_job_failed_has_listeners_registered(): void
    {
        $this->assertTrue(
            Event::hasListeners(JobFailed::class),
            'JobFailed debe tener listeners registrados (ReportFailedQueueJob)'
        );
    }

    // ──────────────────────────────────────────────────────────
    // 4. Conteos exactos de listeners por evento
    //    Detecta si alguien registra un segundo provider y duplica.
    // ──────────────────────────────────────────────────────────

    public function test_ticket_created_has_exactly_four_listeners(): void
    {
        $count = count(Event::getListeners(TicketCreated::class));

        $this->assertSame(
            4,
            $count,
            'TicketCreated debe tener exactamente 4 listeners (Fase 4): '.
            'GenerateEmbeddingOnTicketCreated, DispatchDuplicateDetectionOnTicketCreated, '.
            'CreateInAppNotificationOnTicketCreated, SendFcmPushOnTicketCreated. '.
            'Si hay 8, un segundo provider fue registrado y duplicó todos.'
        );
    }

    public function test_ticket_state_changed_has_exactly_two_listeners(): void
    {
        $count = count(Event::getListeners(TicketStateChanged::class));

        $this->assertSame(
            2,
            $count,
            'TicketStateChanged debe tener exactamente 2 listeners (Fase 4): '.
            'CreateInAppNotificationOnTicketStateChanged, SendFcmPushOnTicketStateChanged'
        );
    }

    public function test_ticket_resolved_has_exactly_one_listener(): void
    {
        $count = count(Event::getListeners(TicketResolved::class));

        $this->assertSame(
            1,
            $count,
            'TicketResolved debe tener exactamente 1 listener: UpdateRecurrenceOnTicketResolved'
        );
    }

    public function test_ticket_assigned_has_exactly_two_listeners(): void
    {
        $count = count(Event::getListeners(TicketAssigned::class));

        $this->assertSame(
            2,
            $count,
            'TicketAssigned debe tener exactamente 2 listeners (Fase 4): '.
            'CreateInAppNotificationOnTicketAssigned, SendFcmPushOnTicketAssigned'
        );
    }

    public function test_duplicate_detected_has_exactly_one_listener(): void
    {
        $count = count(Event::getListeners(DuplicateDetected::class));

        $this->assertSame(
            1,
            $count,
            'DuplicateDetected debe tener exactamente 1 listener: LogDuplicateDetectionAudit'
        );
    }

    // ──────────────────────────────────────────────────────────
    // 5. Listeners resolvables y callables por el dispatcher
    // ──────────────────────────────────────────────────────────

    public function test_ticket_created_listeners_are_callable_by_the_dispatcher(): void
    {
        $expectedListeners = [
            GenerateEmbeddingOnTicketCreated::class,
            DispatchDuplicateDetectionOnTicketCreated::class,
            CreateInAppNotificationOnTicketCreated::class,
            SendFcmPushOnTicketCreated::class,
        ];

        $registered = Event::getListeners(TicketCreated::class);

        $this->assertCount(4, $registered);
        foreach ($registered as $listener) {
            $this->assertIsCallable($listener);
        }

        foreach ($expectedListeners as $listenerClass) {
            $instance = $this->app->make($listenerClass);
            $this->assertInstanceOf($listenerClass, $instance);
        }
    }

    public function test_all_expected_listener_classes_are_resolvable_by_container(): void
    {
        $allListeners = [
            GenerateEmbeddingOnTicketCreated::class,
            DispatchDuplicateDetectionOnTicketCreated::class,
            CreateInAppNotificationOnTicketCreated::class,
            SendFcmPushOnTicketCreated::class,
            CreateInAppNotificationOnTicketStateChanged::class,
            SendFcmPushOnTicketStateChanged::class,
            UpdateRecurrenceOnTicketResolved::class,
            CreateInAppNotificationOnTicketAssigned::class,
            SendFcmPushOnTicketAssigned::class,
            LogDuplicateDetectionAudit::class,
            ReportFailedQueueJob::class,
        ];

        foreach ($allListeners as $listenerClass) {
            $instance = $this->app->make($listenerClass);
            $this->assertInstanceOf(
                $listenerClass,
                $instance,
                "El listener {$listenerClass} no pudo ser resuelto por el contenedor"
            );
        }
    }

    // ──────────────────────────────────────────────────────────
    // 6. shouldDiscoverEvents = false en App\Providers\ESP
    //    Garantiza que auto-discovery no activa ningún provider extra.
    // ──────────────────────────────────────────────────────────

    public function test_app_providers_event_service_provider_has_discovery_disabled(): void
    {
        $provider = new EventServiceProvider($this->app);

        $this->assertFalse(
            $provider->shouldDiscoverEvents(),
            'App\Providers\EventServiceProvider debe tener shouldDiscoverEvents=false.'
        );
    }
}
