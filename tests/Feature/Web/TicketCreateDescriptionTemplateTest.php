<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TicketCreateDescriptionTemplateTest extends TestCase
{
    use RefreshDatabase;

    // ── Template content ──────────────────────────────────────────────────────

    public function test_create_form_renders_description_template_by_default(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $this->actingAs($reporter)
            ->get(route('tickets.create'))
            ->assertOk()
            ->assertSee('¿Qué ocurre?', false);
    }

    public function test_template_contains_when_question(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $this->actingAs($reporter)
            ->get(route('tickets.create'))
            ->assertOk()
            ->assertSee('¿Cuándo ocurre o desde cuándo empezó?', false);
    }

    public function test_template_contains_impact_question(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $this->actingAs($reporter)
            ->get(route('tickets.create'))
            ->assertOk()
            ->assertSee('¿A quién o qué actividad afecta?', false);
    }

    public function test_template_contains_reproduction_question(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $this->actingAs($reporter)
            ->get(route('tickets.create'))
            ->assertOk()
            ->assertSee('¿Cómo se reproduce o en qué momento pasa?', false);
    }

    public function test_template_contains_attempted_question(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $this->actingAs($reporter)
            ->get(route('tickets.create'))
            ->assertOk()
            ->assertSee('¿Qué se intentó antes de reportarlo?', false);
    }

    public function test_template_contains_error_message_field(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $this->actingAs($reporter)
            ->get(route('tickets.create'))
            ->assertOk()
            ->assertSee('Mensaje de error o evidencia relevante:', false);
    }

    // ── Template exclusions ───────────────────────────────────────────────────

    public function test_template_does_not_ask_where_it_occurs(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $this->actingAs($reporter)
            ->get(route('tickets.create'))
            ->assertOk()
            ->assertDontSee('¿Dónde ocurre?', false);
    }

    public function test_template_does_not_repeat_category_field(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $response = $this->actingAs($reporter)
            ->get(route('tickets.create'))
            ->assertOk();

        $content = $response->getContent();
        $this->assertStringNotContainsString('Categoría:', $content);
    }

    public function test_template_does_not_repeat_priority_field(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $response = $this->actingAs($reporter)
            ->get(route('tickets.create'))
            ->assertOk();

        $content = $response->getContent();
        $this->assertStringNotContainsString('Prioridad:', $content);
    }

    // ── Button render ─────────────────────────────────────────────────────────

    public function test_clear_button_is_rendered(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $this->actingAs($reporter)
            ->get(route('tickets.create'))
            ->assertOk()
            ->assertSee('Borrar guía', false);
    }

    public function test_clear_button_has_type_button(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $response = $this->actingAs($reporter)
            ->get(route('tickets.create'))
            ->assertOk();

        $response->assertSee('type="button"', false);
    }

    public function test_clear_button_has_aria_controls_pointing_to_textarea(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $response = $this->actingAs($reporter)
            ->get(route('tickets.create'))
            ->assertOk();

        $response->assertSee('aria-controls="create-description"', false);
    }

    // ── Textarea constraints ──────────────────────────────────────────────────

    public function test_textarea_has_maxlength_2000(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $response = $this->actingAs($reporter)
            ->get(route('tickets.create'))
            ->assertOk();

        $response->assertSee('maxlength="2000"', false);
    }

    // ── Help text ─────────────────────────────────────────────────────────────

    public function test_help_text_is_updated(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $this->actingAs($reporter)
            ->get(route('tickets.create'))
            ->assertOk()
            ->assertSee('Describe el síntoma, cuándo ocurre, a quién afecta y qué se intentó.', false);
    }

    // ── old() / validation ────────────────────────────────────────────────────

    public function test_old_description_replaces_template_after_validation_error(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $customDescription = 'Mi descripcion personalizada que tiene mas de veinte caracteres.';

        $response = $this->actingAs($reporter)
            ->withSession(['_old_input' => ['description' => $customDescription]])
            ->get(route('tickets.create'))
            ->assertOk();

        $response->assertSee($customDescription, false);

        // The textarea VALUE must be the custom description, not the template.
        // The template may still appear in data-description-template-default (by design).
        $content = $response->getContent();
        $this->assertStringContainsString(
            '>'.$customDescription.'</textarea>',
            $content
        );
    }

    public function test_submitting_empty_description_fails_validation(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $this->actingAs($reporter)
            ->post(route('tickets.store'), [
                'title' => 'Título válido para la prueba',
                'description' => '',
                'location_id' => $location->id,
                'category_id' => $category->id,
                'priority' => 'medium',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertSessionHasErrors('description');
    }

    public function test_submitting_custom_description_succeeds(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $this->actingAs($reporter)
            ->post(route('tickets.store'), [
                'title' => 'Título válido con más de cinco',
                'description' => 'Descripcion personalizada con mas de veinte caracteres claramente.',
                'location_id' => $location->id,
                'category_id' => $category->id,
                'priority' => 'medium',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    // ── Duplicate precheck preserves description ──────────────────────────────

    public function test_duplicate_precheck_preserves_user_description(): void
    {
        $reporter = $this->createUserWithRole('reporter');
        $location = $this->createLocation();
        $category = $this->createCategory();

        $customDescription = 'Descripcion escrita por el usuario con mas de veinte caracteres validos.';

        $response = $this->actingAs($reporter)
            ->post(route('tickets.store'), [
                'title' => 'Proyector aula 101 no enciende',
                'description' => $customDescription,
                'location_id' => $location->id,
                'category_id' => $category->id,
                'priority' => 'medium',
                'idempotency_key' => (string) Str::uuid(),
            ]);

        if ($response->isRedirect(route('tickets.create'))) {
            $response->assertSessionHas('_old_input');

            $this->actingAs($reporter)
                ->withSession($response->getSession()->all())
                ->get(route('tickets.create'))
                ->assertOk()
                ->assertSee($customDescription, false);
        } else {
            $response->assertRedirect();
        }
    }

    // ── No debug residual ─────────────────────────────────────────────────────

    public function test_no_debug_content_is_visible(): void
    {
        $reporter = $this->createUserWithRole('reporter');

        $response = $this->actingAs($reporter)
            ->get(route('tickets.create'))
            ->assertOk();

        $content = $response->getContent();
        $this->assertStringNotContainsString('console.log', $content);
        $this->assertStringNotContainsString('TODO', $content);
        $this->assertStringNotContainsString('FIXME', $content);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createUserWithRole(string $role): User
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /** @param array<string, mixed> $overrides */
    private function createLocation(array $overrides = []): Location
    {
        return Location::create(array_merge([
            'name' => 'Aula Innovacion',
            'building' => 'Edificio A',
            'floor' => '2',
            'room_code' => 'A-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-'.Str::lower(Str::random(12)),
            'is_active' => true,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function createCategory(array $overrides = []): Category
    {
        return Category::create(array_merge([
            'name' => 'Categoria '.Str::lower(Str::random(8)),
            'icon' => 'bolt',
            'description' => 'Incidencias electricas',
        ], $overrides));
    }
}
