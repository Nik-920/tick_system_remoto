<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
        $response->assertSee('Recuperar contrasena');
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->get(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]));

        $response->assertOk();
        $response->assertSee('Restablecer contrasena');
    }

    public function test_password_reset_link_can_be_requested_for_existing_email(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHas('status', 'Si el correo existe en el sistema, enviaremos un enlace de recuperacion.');
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $user->email,
        ]);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_reset_link_returns_generic_message_for_unknown_email(): void
    {
        Notification::fake();

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => 'no-existe@example.com',
        ]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', 'Si el correo existe en el sistema, enviaremos un enlace de recuperacion.');
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'no-existe@example.com',
        ]);
        Notification::assertNothingSent();
    }

    public function test_password_reset_link_request_is_rate_limited_after_five_attempts(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $response = $this->from(route('password.request'))->post(route('password.email'), [
                'email' => $user->email,
            ]);

            $response->assertRedirect(route('password.request'));
        }

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $response->assertStatus(429);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $token = Password::createToken($user);

        $response = $this->from(route('password.reset', ['token' => $token]))->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nuevaClave123',
            'password_confirmation' => 'nuevaClave123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', 'Tu contrasena fue restablecida correctamente.');

        $user->refresh();
        $this->assertTrue(Hash::check('nuevaClave123', $user->password));

        $loginResponse = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'nuevaClave123',
        ]);

        $loginResponse->assertRedirect(route('dashboard.index'));
    }

    public function test_password_cannot_be_reset_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->from(route('password.reset', ['token' => 'token-invalido']))->post(route('password.update'), [
            'token' => 'token-invalido',
            'email' => $user->email,
            'password' => 'nuevaClave123',
            'password_confirmation' => 'nuevaClave123',
        ]);

        $response->assertRedirect(route('password.reset', ['token' => 'token-invalido']));
        $response->assertSessionHasErrors('email');
    }
}
