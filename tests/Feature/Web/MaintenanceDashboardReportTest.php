<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceDashboardReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));
    }

    public function test_guest_cannot_access_report_pdf(): void
    {
        $this->get(route('dashboard.maintenance.report.pdf'))->assertRedirect(route('login'));
    }

    public function test_reporter_cannot_access_report_pdf(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $this->actingAs($reporter)
            ->get(route('dashboard.maintenance.report.pdf'))
            ->assertForbidden();
    }

    public function test_admin_cannot_access_maintenance_report_pdf(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin)
            ->get(route('dashboard.maintenance.report.pdf'))
            ->assertForbidden();
    }

    public function test_maintenance_can_download_report_pdf(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');

        $response = $this->actingAs($maintenance)
            ->get(route('dashboard.maintenance.report.pdf'));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
    }

    public function test_report_pdf_filename_reflects_selected_range(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');

        $response = $this->actingAs($maintenance)
            ->get(route('dashboard.maintenance.report.pdf', [
                'preset' => 'custom',
                'from' => '2026-06-01',
                'to' => '2026-06-10',
            ]));

        $response->assertDownload('informe-mantenimiento-2026-06-01_2026-06-10.pdf');
    }

    public function test_report_pdf_can_be_streamed_inline(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');

        $response = $this->actingAs($maintenance)
            ->get(route('dashboard.maintenance.report.pdf', ['inline' => 1]));

        $response->assertOk();
        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
    }

    public function test_report_pdf_rejects_inverted_range(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');

        $this->actingAs($maintenance)
            ->get(route('dashboard.maintenance.report.pdf', ['from' => '2026-06-10', 'to' => '2026-06-01']))
            ->assertSessionHasErrors('to');
    }

    public function test_preview_contains_kpis_and_range(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');

        $response = $this->actingAs($maintenance)
            ->get(route('dashboard.maintenance.report', ['preset' => 'custom', 'from' => '2026-06-01', 'to' => '2026-06-10']));

        $response->assertOk();
        $response->assertSeeText('Indicadores del periodo');
        $response->assertSeeText('Resumen ejecutivo');
        $response->assertSee('2026-06-01');
        $response->assertSee('2026-06-10');
    }

    public function test_preview_does_not_leak_other_technician_tickets(): void
    {
        $maintenance = $this->createUserWithRole('maintenance');
        $other = $this->createUserWithRole('maintenance');
        $category = $this->createCategory('Hardware');

        $mine = Ticket::create([
            'title' => 'Mi pendiente visible',
            'description' => 'Debe aparecer',
            'reporter_id' => $maintenance->id,
            'location_id' => $this->createLocation('Lab Propio', 'P-1')->id,
            'category_id' => $category->id,
            'state' => 'in_progress',
            'priority' => 'high',
        ]);
        $mine->forceFill(['assigned_to' => $maintenance->id])->save();

        $foreign = Ticket::create([
            'title' => 'Confidencial de otro tecnico',
            'description' => 'No debe filtrarse',
            'reporter_id' => $other->id,
            'location_id' => $this->createLocation('Lab Ajeno', 'P-2')->id,
            'category_id' => $category->id,
            'state' => 'in_progress',
            'priority' => 'critical',
        ]);
        $foreign->forceFill(['assigned_to' => $other->id])->save();

        $response = $this->actingAs($maintenance)
            ->get(route('dashboard.maintenance.report'));

        $response->assertOk();
        $response->assertSeeText($mine->title);
        $response->assertDontSeeText($foreign->title);
    }

    private function createUserWithRole(string $role): User
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function createLocation(string $name, string $roomCode): Location
    {
        return Location::create([
            'name' => $name,
            'building' => 'Edificio Z',
            'floor' => '1',
            'room_code' => $roomCode,
            'qr_token' => 'qr-'.strtolower(str_replace([' ', '_'], '-', $roomCode)),
            'is_active' => true,
        ]);
    }

    private function createCategory(string $name): Category
    {
        return Category::create([
            'name' => $name,
            'icon' => 'wrench',
            'description' => 'Categoria de prueba',
        ]);
    }
}
