<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Temporary static prototype (V2) of the maintenance "Tickets" tab.
 *
 * This controller serves a purely visual mock-up that reproduces the design
 * reference at img/Manitence/Pestaña de Tickets.png. It carries NO business
 * logic: every value below is hard-coded sample data so the layout, hierarchy
 * and interactions can be validated before wiring real queries.
 *
 * It deliberately does NOT touch TicketController, TicketIndexQuery, policies
 * or the live /tickets route. Once the design is approved and folded into the
 * real tickets index, remove this controller together with its route, view,
 * CSS module and feature test.
 */
final class TicketMaintenanceV2Controller extends Controller
{
    public function __invoke(): View
    {
        return view('tickets.maintenance-v2', [
            'currentUser' => $this->currentUser(),
            'tabs' => $this->tabs(),
            'chips' => $this->chips(),
            'summary' => $this->summary(),
            'tickets' => $this->tickets(),
            'insights' => $this->insights(),
        ]);
    }

    /**
     * Static identity shown in the topbar (matches the mock-up, not the
     * authenticated session).
     *
     * @return array<string, string>
     */
    private function currentUser(): array
    {
        return [
            'name' => 'Ana Torres',
            'role' => 'Maintenance',
            'initials' => 'AT',
        ];
    }

    /**
     * Quick-view segmented tabs above the toolbar.
     *
     * @return list<array{key: string, label: string, active: bool}>
     */
    private function tabs(): array
    {
        return [
            ['key' => 'mine', 'label' => 'Mis tickets', 'active' => true],
            ['key' => 'available', 'label' => 'Disponibles para tomar', 'active' => false],
            ['key' => 'all', 'label' => 'Todos visibles', 'active' => false],
        ];
    }

    /**
     * Quick filter chips (the row under the search toolbar).
     *
     * @return list<array{key: string, label: string, count: int, tone: string, active: bool}>
     */
    private function chips(): array
    {
        return [
            ['key' => 'all', 'label' => 'Todos', 'count' => 9, 'tone' => 'neutral', 'active' => true],
            ['key' => 'in_progress', 'label' => 'En progreso', 'count' => 3, 'tone' => 'progress', 'active' => false],
            ['key' => 'high', 'label' => 'Alta prioridad', 'count' => 2, 'tone' => 'high', 'active' => false],
            ['key' => 'available', 'label' => 'Disponibles', 'count' => 4, 'tone' => 'available', 'active' => false],
            ['key' => 'duplicates', 'label' => 'Posibles duplicados', 'count' => 1, 'tone' => 'warning', 'active' => false],
        ];
    }

    /**
     * Summary stat cards.
     *
     * @return list<array{label: string, value: int, icon: string, tone: string}>
     */
    private function summary(): array
    {
        return [
            ['label' => 'Tickets visibles', 'value' => 9, 'icon' => 'ticket', 'tone' => 'primary'],
            ['label' => 'En progreso', 'value' => 3, 'icon' => 'clock', 'tone' => 'progress'],
            ['label' => 'Asignados a mí', 'value' => 5, 'icon' => 'list-checks', 'tone' => 'info'],
            ['label' => 'Disponibles para tomar', 'value' => 4, 'icon' => 'inbox', 'tone' => 'available'],
        ];
    }

    /**
     * Prioritised queue: the operational ticket rows.
     *
     * @return list<array{
     *     title: string,
     *     location: string,
     *     category: string,
     *     subcategory: string,
     *     description: string,
     *     icon: string,
     *     priority: string,
     *     priority_label: string,
     *     state: string,
     *     state_label: string,
     *     assignment: string,
     *     assignment_label: string,
     *     assignment_note: string,
     *     created_at: string,
     *     duplicate: bool,
     *     action: string
     * }>
     */
    private function tickets(): array
    {
        return [
            [
                'title' => 'PC con pantalla azul',
                'location' => 'Laboratorio 1',
                'category' => 'Hardware',
                'subcategory' => 'PC',
                'description' => 'La computadora muestra pantalla azul al iniciar sesión.',
                'icon' => 'monitor',
                'priority' => 'high',
                'priority_label' => 'Alta',
                'state' => 'in_progress',
                'state_label' => 'En progreso',
                'assignment' => 'mine',
                'assignment_label' => 'Asignado a mí',
                'assignment_note' => 'Tomado',
                'created_at' => '28/05/2026',
                'duplicate' => false,
                'action' => 'manage',
            ],
            [
                'title' => 'PC4 con virus',
                'location' => 'Laboratorio 3',
                'category' => 'Software',
                'subcategory' => 'Seguridad',
                'description' => 'Equipo con comportamiento inusual y posibles virus.',
                'icon' => 'shield-alert',
                'priority' => 'high',
                'priority_label' => 'Alta',
                'state' => 'open',
                'state_label' => 'Abierto',
                'assignment' => 'mine',
                'assignment_label' => 'Asignado a mí',
                'assignment_note' => 'Tomado',
                'created_at' => '28/05/2026',
                'duplicate' => false,
                'action' => 'manage',
            ],
            [
                'title' => 'Mesa rota',
                'location' => 'Laboratorio 2',
                'category' => 'Mobiliario',
                'subcategory' => 'Mesa',
                'description' => 'La mesa presenta una pata rota, requiere reparación.',
                'icon' => 'armchair',
                'priority' => 'medium',
                'priority_label' => 'Media',
                'state' => 'in_progress',
                'state_label' => 'En progreso',
                'assignment' => 'mine',
                'assignment_label' => 'Asignado a mí',
                'assignment_note' => 'Tomado',
                'created_at' => '27/05/2026',
                'duplicate' => false,
                'action' => 'manage',
            ],
            [
                'title' => 'Proyector A-201 no enciende',
                'location' => 'Laboratorio 1',
                'category' => 'Equipos',
                'subcategory' => 'Proyector',
                'description' => 'El proyector no enciende y no responde al control remoto.',
                'icon' => 'projector',
                'priority' => 'medium',
                'priority_label' => 'Media',
                'state' => 'open',
                'state_label' => 'Abierto',
                'assignment' => 'available',
                'assignment_label' => 'Disponible',
                'assignment_note' => 'Para tomar',
                'created_at' => '27/05/2026',
                'duplicate' => true,
                'action' => 'take',
            ],
            [
                'title' => 'Cable HDMI dañado',
                'location' => 'Laboratorio 4',
                'category' => 'Conectividad',
                'subcategory' => 'Cables',
                'description' => 'Cable HDMI presenta daño en el conector.',
                'icon' => 'cable',
                'priority' => 'low',
                'priority_label' => 'Baja',
                'state' => 'open',
                'state_label' => 'Abierto',
                'assignment' => 'available',
                'assignment_label' => 'Disponible',
                'assignment_note' => 'Para tomar',
                'created_at' => '26/05/2026',
                'duplicate' => false,
                'action' => 'take',
            ],
        ];
    }

    /**
     * Right-hand "Insights operativos" panel data.
     *
     * @return array{
     *     priority: array{total: int, segments: list<array{key: string, label: string, count: int, percent: int}>},
     *     labs: array{total: int, items: list<array{label: string, count: int}>},
     *     states: list<array{label: string, count: int, tone: string}>,
     *     duplicates: int,
     *     avg_time: array{value: string, status: string}
     * }
     */
    private function insights(): array
    {
        return [
            'priority' => [
                'total' => 9,
                'segments' => [
                    ['key' => 'high', 'label' => 'Alta', 'count' => 1, 'percent' => 11],
                    ['key' => 'medium', 'label' => 'Media', 'count' => 7, 'percent' => 78],
                    ['key' => 'low', 'label' => 'Baja', 'count' => 1, 'percent' => 11],
                ],
            ],
            'labs' => [
                'total' => 9,
                'items' => [
                    ['label' => 'Laboratorio 1', 'count' => 4],
                    ['label' => 'Laboratorio 2', 'count' => 3],
                    ['label' => 'Laboratorio 3', 'count' => 1],
                    ['label' => 'Laboratorio 4', 'count' => 1],
                ],
            ],
            'states' => [
                ['label' => 'Abiertos', 'count' => 6, 'tone' => 'open'],
                ['label' => 'En progreso', 'count' => 3, 'tone' => 'progress'],
                ['label' => 'Resueltos hoy', 'count' => 0, 'tone' => 'resolved'],
                ['label' => 'Rechazados', 'count' => 0, 'tone' => 'rejected'],
            ],
            'duplicates' => 1,
            'avg_time' => [
                'value' => '2h 45m',
                'status' => 'En progreso',
            ],
        ];
    }
}
