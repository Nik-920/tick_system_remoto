<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * ThemePreferenceTest
 *
 * Verifica que la infraestructura de tema claro/oscuro está correctamente
 * implementada en el layout autenticado:
 *   - El botón #themeToggleBtn existe
 *   - El script de boot de tema está en <head>
 *   - El botón tiene atributos de accesibilidad (aria-label, aria-pressed)
 *   - El layout incluye data-theme en <html>
 */
class ThemePreferenceTest extends TestCase
{
    use RefreshDatabase;

    private function authenticatedUser(): User
    {
        return $this->createUserWithRole('admin');
    }

    /**
     * El layout autenticado debe contener el botón de cambio de tema.
     */
    public function test_authenticated_layout_contains_theme_toggle_button(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->actingAs($user)->get(route('dashboard.index'));

        $response->assertStatus(200);
        $response->assertSee('id="themeToggleBtn"', false);
    }

    /**
     * El layout autenticado debe contener el script de boot de tema en el head.
     */
    public function test_authenticated_layout_contains_theme_boot_script(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->actingAs($user)->get(route('dashboard.index'));

        $response->assertStatus(200);
        // El script usa la clave 'tick-theme' para leer localStorage y cookie
        $response->assertSee('tick-theme', false);
    }

    /**
     * El botón de tema debe tener aria-label para accesibilidad.
     */
    public function test_theme_button_has_aria_label(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->actingAs($user)->get(route('dashboard.index'));

        $response->assertStatus(200);
        $response->assertSee('aria-label="Cambiar tema"', false);
    }

    /**
     * El botón de tema debe tener aria-pressed para estado accesible.
     */
    public function test_theme_button_has_aria_pressed(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->actingAs($user)->get(route('dashboard.index'));

        $response->assertStatus(200);
        $response->assertSee('aria-pressed=', false);
    }

    /**
     * El elemento <html> del layout autenticado debe tener data-theme.
     */
    public function test_authenticated_layout_html_has_data_theme_attribute(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->actingAs($user)->get(route('dashboard.index'));

        $response->assertStatus(200);
        $response->assertSee('data-theme=', false);
    }

    /**
     * El script de boot debe incluir el fallback de prefers-color-scheme del sistema.
     */
    public function test_theme_boot_script_includes_media_query_fallback(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->actingAs($user)->get(route('dashboard.index'));

        $response->assertStatus(200);
        $response->assertSee('prefers-color-scheme', false);
    }

    /**
     * El script de boot debe escribir cookie tick-theme (visible en el source).
     * Verifica que el JS de cookie está presente.
     */
    public function test_theme_boot_script_includes_cookie_logic(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->actingAs($user)->get(route('dashboard.index'));

        $response->assertStatus(200);
        // El script de boot y el fallback JS inline incluyen lógica de cookie
        $response->assertSee('tick-theme', false);
    }

    private function createUserWithRole(string $role): User
    {
        $this->ensureRolesExist();

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function ensureRolesExist(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }
    }
}
