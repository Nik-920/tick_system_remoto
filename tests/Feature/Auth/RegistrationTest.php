<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
        $response->assertSee('Crear cuenta');
    }

    public function test_new_users_can_register_and_receive_reporter_role(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Usuario Reporter',
            'email' => 'reporter@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard.index'));

        $user = User::query()->where('email', 'reporter@example.com')->first();

        $this->assertNotNull($user);
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasRole('reporter'));
    }
}
