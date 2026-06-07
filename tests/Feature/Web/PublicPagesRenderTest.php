<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke tests guarding that the public/guest entry points keep rendering after
 * the responsive-hardening pass (no broken Blade, no 500s). These are intentionally
 * light — they assert the page renders and shows an anchor element, not CSS.
 */
class PublicPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_welcome(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_guest_can_view_login(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSeeText('Iniciar sesión');
    }

    public function test_guest_can_view_register(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
        $response->assertSeeText('Crear cuenta');
    }

    public function test_guest_can_view_forgot_password(): void
    {
        $this->get(route('password.request'))->assertOk();
    }
}
