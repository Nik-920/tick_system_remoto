<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * "Guía del reporter" — a static help page for the reporter role: how to file a
 * good incident report (steps, best practices, good vs poor examples, available
 * categories and important notes). It is the destination of the "Ver guía de
 * reporte" links across the reporter surfaces.
 *
 * FIRST VISUAL PHASE — informational only. All content is static; nothing reads
 * or mutates data, and NO maintenance action is exposed. The route middleware
 * (auth + role:reporter) is the access gate.
 */
class ReporterGuideController extends Controller
{
    public function __invoke(): View
    {
        return view('reporter.guide', [
            'steps' => $this->steps(),
            'practices' => $this->practices(),
            'examples' => $this->examples(),
            'categories' => $this->categories(),
            'info' => $this->info(),
        ]);
    }

    /**
     * The five steps to file an incident.
     *
     * @return list<array{icon: string, title: string, desc: string}>
     */
    private function steps(): array
    {
        return [
            ['icon' => 'file-pen',     'title' => 'Describe el problema', 'desc' => 'Explica con claridad qué ocurre y desde cuándo.'],
            ['icon' => 'map-pin',      'title' => 'Indica la ubicación',  'desc' => 'Selecciona el laboratorio o aula afectada.'],
            ['icon' => 'tag',          'title' => 'Elige una categoría',  'desc' => 'Clasifica el tipo de incidencia.'],
            ['icon' => 'camera',       'title' => 'Agrega evidencias',    'desc' => 'Sube fotos o capturas del problema.'],
            ['icon' => 'send',         'title' => 'Envía tu reporte',     'desc' => 'Confirma y da seguimiento al estado.'],
        ];
    }

    /**
     * Best practices checklist.
     *
     * @return list<string>
     */
    private function practices(): array
    {
        return [
            'Incluye detalles concretos: qué falla, desde cuándo y con qué frecuencia.',
            'Usa un título breve y descriptivo en lugar de frases vagas.',
            'Indica la ubicación exacta (laboratorio, aula, equipo).',
            'Adjunta fotos o capturas claras que muestren el problema.',
            'Revisa si ya existe un reporte similar para evitar duplicados.',
        ];
    }

    /**
     * Good vs poor report examples.
     *
     * @return array{good: array{title: string, text: string}, bad: array{title: string, text: string}}
     */
    private function examples(): array
    {
        return [
            'good' => [
                'title' => 'Buen reporte',
                'text' => 'La PC #12 del Laboratorio 1 no enciende desde ayer. La luz de encendido parpadea en rojo. Adjunto foto del equipo.',
            ],
            'bad' => [
                'title' => 'Reporte poco claro',
                'text' => 'La compu no sirve. Arréglenla.',
            ],
        ];
    }

    /**
     * Available categories (for orientation).
     *
     * @return list<array{label: string, icon: string, tone: string}>
     */
    private function categories(): array
    {
        return [
            ['label' => 'Hardware',     'icon' => 'monitor',   'tone' => 'primary'],
            ['label' => 'Conectividad', 'icon' => 'cable',     'tone' => 'purple'],
            ['label' => 'Mobiliario',   'icon' => 'armchair',  'tone' => 'warning'],
            ['label' => 'Equipos',      'icon' => 'projector', 'tone' => 'info'],
            ['label' => 'Servicios',    'icon' => 'droplet',   'tone' => 'high'],
        ];
    }

    /**
     * Important notes about the reporting process.
     *
     * @return list<array{icon: string, text: string}>
     */
    private function info(): array
    {
        return [
            ['icon' => 'bell',         'text' => 'Recibirás una notificación cada vez que cambie el estado de tu ticket.'],
            ['icon' => 'clock',        'text' => 'Los tickets se atienden según su prioridad y orden de llegada.'],
            ['icon' => 'list-checks',  'text' => 'Puedes dar seguimiento a tus reportes desde "Mis tickets" en cualquier momento.'],
            ['icon' => 'shield-check', 'text' => 'Si el problema persiste después de resolverse, coméntalo en el ticket para reabrirlo.'],
        ];
    }
}
