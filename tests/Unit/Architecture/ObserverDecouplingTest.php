<?php

namespace Tests\Unit\Architecture;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Observer — Criterio 10 / Brecha 6
 *
 * Test arquitectónico que verifica que ningún emisor (event, service ni
 * controller) importa un listener concreto de App\Listeners.
 *
 * Si este test falla, significa que se ha introducido acoplamiento inverso
 * en el patrón Observer: el sujeto/emisor estaría conociendo a sus
 * observadores, violando el principio de desacoplamiento del patrón.
 *
 * Este test sólo escanea app/ (código de producción). No escanea tests/.
 */
final class ObserverDecouplingTest extends TestCase
{
    // ──────────────────────────────────────────────────────────
    // Los eventos no deben importar listeners
    // ──────────────────────────────────────────────────────────

    public function test_events_do_not_import_listeners(): void
    {
        foreach (File::allFiles(app_path('Events')) as $file) {
            $contents = File::get($file->getPathname());

            $this->assertStringNotContainsString(
                'App\\Listeners\\',
                $contents,
                "Event file imports a listener: {$file->getPathname()}"
            );
        }
    }

    // ──────────────────────────────────────────────────────────
    // Los servicios de tickets no deben importar listeners
    // ──────────────────────────────────────────────────────────

    public function test_ticket_services_do_not_import_listeners(): void
    {
        foreach (File::allFiles(app_path('Services/Tickets')) as $file) {
            $contents = File::get($file->getPathname());

            $this->assertStringNotContainsString(
                'App\\Listeners\\',
                $contents,
                "Ticket service imports a listener: {$file->getPathname()}"
            );
        }
    }

    // ──────────────────────────────────────────────────────────
    // Los controllers no deben importar listeners concretos
    // ──────────────────────────────────────────────────────────

    public function test_controllers_do_not_import_observer_listeners(): void
    {
        foreach (File::allFiles(app_path('Http/Controllers')) as $file) {
            $contents = File::get($file->getPathname());

            $this->assertStringNotContainsString(
                'App\\Listeners\\',
                $contents,
                "Controller imports a listener directly: {$file->getPathname()}"
            );
        }
    }

    // ──────────────────────────────────────────────────────────
    // Los jobs no deben importar listeners concretos
    // ──────────────────────────────────────────────────────────

    public function test_jobs_do_not_import_observer_listeners(): void
    {
        foreach (File::allFiles(app_path('Jobs')) as $file) {
            $contents = File::get($file->getPathname());

            $this->assertStringNotContainsString(
                'App\\Listeners\\',
                $contents,
                "Job imports a listener directly: {$file->getPathname()}"
            );
        }
    }
}
