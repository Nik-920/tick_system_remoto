<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\GuestTicketContact;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Reporte QR público (FEATURE_PUBLIC_QR_REPORT).
 *
 * Contrato bajo prueba:
 * 1. Flag OFF → las rutas públicas devuelven 404 (el feature no existe).
 * 2. Flag ON → un invitado ve el formulario de la ubicación del QR.
 * 3. El envío crea un ticket normal a nombre del usuario sistema (rol
 *    reporter) + un GuestTicketContact con código de seguimiento.
 * 4. Honeypot lleno → respuesta de éxito falsa, sin crear nada.
 * 5. La página de seguimiento muestra el estado con un código válido.
 * 6. Un usuario logueado es redirigido al flujo autenticado (/scan).
 */
class PublicQrReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('reporter', 'web');
    }

    public function test_public_routes_return_404_when_flag_is_off(): void
    {
        config(['tickets.public_qr_report.enabled' => false]);
        $location = $this->makeLocation();

        $this->get('/r/'.$location->qr_token)->assertNotFound();
        $this->get('/r/seguimiento')->assertNotFound();
    }

    public function test_guest_sees_report_form_when_flag_is_on(): void
    {
        config(['tickets.public_qr_report.enabled' => true]);
        $location = $this->makeLocation();
        $this->makeCategory();

        $response = $this->get('/r/'.$location->qr_token);

        $response->assertOk();
        $response->assertSee('Reportar incidencia');
        $response->assertSee($location->room_code);
    }

    public function test_guest_submission_creates_ticket_with_system_user_and_tracking_code(): void
    {
        config(['tickets.public_qr_report.enabled' => true]);
        $location = $this->makeLocation();
        $category = $this->makeCategory();

        $response = $this->post('/r/'.$location->qr_token, [
            'category_id' => $category->id,
            'description' => 'El proyector del laboratorio no enciende desde temprano.',
            'contact_email' => 'invitado@example.com',
            'website' => '',
        ]);

        $response->assertRedirect(route('public.qr.success'));
        $response->assertSessionHas('tracking_code');

        $systemUser = User::query()
            ->where('email', config('tickets.public_qr_report.system_user_email'))
            ->firstOrFail();
        $this->assertTrue($systemUser->hasRole('reporter'));

        $ticket = Ticket::query()->where('reporter_id', $systemUser->id)->firstOrFail();
        $this->assertSame($location->id, $ticket->location_id);
        $this->assertSame($category->id, $ticket->category_id);
        $this->assertSame('open', $ticket->state);

        $contact = GuestTicketContact::query()->where('ticket_id', $ticket->id)->firstOrFail();
        $this->assertSame('invitado@example.com', $contact->contact_email);
        $this->assertStringStartsWith('QR-', $contact->tracking_code);
    }

    public function test_honeypot_submission_creates_nothing_but_fakes_success(): void
    {
        config(['tickets.public_qr_report.enabled' => true]);
        $location = $this->makeLocation();
        $category = $this->makeCategory();

        $response = $this->post('/r/'.$location->qr_token, [
            'category_id' => $category->id,
            'description' => 'Descripción de un bot que llenó el honeypot.',
            'website' => 'http://spam.example.com',
        ]);

        $response->assertRedirect(route('public.qr.success'));
        $this->assertSame(0, Ticket::query()->count());
        $this->assertSame(0, GuestTicketContact::query()->count());
    }

    public function test_tracking_page_shows_state_for_valid_code(): void
    {
        config(['tickets.public_qr_report.enabled' => true]);
        $location = $this->makeLocation();
        $category = $this->makeCategory();

        $this->post('/r/'.$location->qr_token, [
            'category_id' => $category->id,
            'description' => 'Se rompió una silla en la última fila del aula.',
            'website' => '',
        ]);

        $contact = GuestTicketContact::query()->firstOrFail();

        $response = $this->get('/r/seguimiento?code='.$contact->tracking_code);

        $response->assertOk();
        $response->assertSee('Abierto');
        $response->assertSee($location->room_code);
    }

    public function test_authenticated_user_is_redirected_to_regular_scan_flow(): void
    {
        config(['tickets.public_qr_report.enabled' => true]);
        $location = $this->makeLocation();

        $user = User::factory()->create();
        $user->assignRole('reporter');

        $this->actingAs($user)
            ->get('/r/'.$location->qr_token)
            ->assertRedirect(route('scan.show', ['token' => $location->qr_token]));
    }

    private function makeLocation(): Location
    {
        return Location::create([
            'name' => 'Laboratorio '.Str::random(4),
            'building' => 'Pabellón B',
            'floor' => '1',
            'room_code' => 'LAB-'.Str::uuid(),
            'qr_token' => 'tok'.Str::random(12),
            'is_active' => true,
        ]);
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Mobiliario-'.Str::uuid(),
            'icon' => 'armchair',
            'description' => 'Categoría de prueba',
        ]);
    }
}
