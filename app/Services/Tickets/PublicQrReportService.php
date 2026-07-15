<?php

declare(strict_types=1);

namespace App\Services\Tickets;

use App\Models\GuestTicketContact;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Reporte QR público (invitados sin cuenta).
 *
 * Crea el ticket a nombre de un usuario sistema con rol reporter, de modo que
 * el resto del pipeline (TicketCreated, dedup IA, auto-asignación por
 * ubicación, notificaciones, policies) funciona exactamente igual que con un
 * ticket normal. Genera además el código de seguimiento del invitado.
 */
class PublicQrReportService
{
    public function __construct(private TicketCreationService $ticketCreationService) {}

    /**
     * @param  array{category_id: string, description: string, contact_email?: string|null}  $payload
     * @param  array<int, UploadedFile>  $mediaFiles
     * @return array{ticket: Ticket, tracking_code: string}
     */
    public function createGuestTicket(
        string $locationId,
        array $payload,
        array $mediaFiles = [],
        string $correlationId = '',
    ): array {
        $systemUser = $this->resolveSystemUser();

        $description = trim((string) $payload['description']);

        $result = $this->ticketCreationService->create(
            $systemUser,
            [
                'title' => Str::limit($description, 80, '…'),
                'description' => $description,
                'location_id' => $locationId,
                'category_id' => (string) $payload['category_id'],
                'priority' => 'medium',
            ],
            $mediaFiles,
            $correlationId,
        );

        $ticket = $result['ticket'];

        $contact = GuestTicketContact::create([
            'ticket_id' => $ticket->id,
            'contact_email' => $payload['contact_email'] ?? null,
            'tracking_code' => $this->generateTrackingCode(),
        ]);

        return [
            'ticket' => $ticket,
            'tracking_code' => $contact->tracking_code,
        ];
    }

    public function findByTrackingCode(string $code): ?GuestTicketContact
    {
        $normalized = strtoupper(trim($code));

        if ($normalized === '') {
            return null;
        }

        return GuestTicketContact::query()
            ->with(['ticket.location', 'ticket.category'])
            ->where('tracking_code', $normalized)
            ->first();
    }

    /**
     * Usuario sistema dueño de los tickets de invitados. Se crea una sola vez
     * (idempotente) con contraseña aleatoria: nadie inicia sesión con él.
     */
    public function resolveSystemUser(): User
    {
        $email = (string) config('tickets.public_qr_report.system_user_email');

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => (string) config('tickets.public_qr_report.system_user_name', 'Reporte QR'),
                'last_name' => (string) config('tickets.public_qr_report.system_user_last_name', 'Público'),
                'password' => Hash::make(Str::random(48)),
            ],
        );

        Role::findOrCreate('reporter', 'web');

        if (method_exists($user, 'hasRole') && ! $user->hasRole('reporter')) {
            $user->assignRole('reporter');
        }

        return $user;
    }

    private function generateTrackingCode(): string
    {
        // Sin vocales (evita formar palabras) ni 0/O/1/I/L (evita confusiones
        // al dictarlo o copiarlo a mano).
        $alphabet = '23456789BCDFGHJKMNPQRSTVWXZ';

        do {
            $suffix = '';
            for ($i = 0; $i < 6; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = 'QR-'.$suffix;
        } while (GuestTicketContact::query()->where('tracking_code', $code)->exists());

        return $code;
    }
}
