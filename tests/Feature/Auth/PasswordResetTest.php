<?php

namespace Tests\Feature\Auth;

use App\Mail\ResetPasswordMail;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
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
        $response->assertSee("Recuperar contrase\u{00F1}a");
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
        $response->assertSee("Restablecer contrase\u{00F1}a");
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
        Notification::assertSentTo($user, ResetPasswordNotification::class);
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

    public function test_login_screen_shows_forgot_password_link(): void
    {
        $response = $this->get(route('login'));

        $response->assertSee(route('password.request'), false);
    }

    public function test_forgot_password_rejects_invalid_email_format(): void
    {
        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_password_reset_email_uses_branded_mailable_with_correct_url(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($user): bool {
            $mail = $notification->toMail($user);

            return $mail instanceof ResetPasswordMail
                && $mail->hasTo($user->email)
                && str_contains($mail->resetUrl, (string) config('app.url'))
                && str_contains($mail->resetUrl, url('/reset-password/'))
                && str_contains($mail->resetUrl, 'email='.rawurlencode($user->email));
        });
    }

    public function test_password_reset_token_is_invalidated_after_use(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'primeraClave123',
            'password_confirmation' => 'primeraClave123',
        ])->assertRedirect(route('login'));

        $response = $this->from(route('password.reset', ['token' => $token]))->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'segundaClave123',
            'password_confirmation' => 'segundaClave123',
        ]);

        $response->assertSessionHasErrors('email');

        $user->refresh();
        $this->assertTrue(Hash::check('primeraClave123', $user->password));
    }

    public function test_password_reset_token_expires_after_configured_ttl(): void
    {
        $user = User::factory()->create();
        $originalPassword = $user->password;
        $token = Password::createToken($user);

        $this->travel(61)->minutes();

        $response = $this->from(route('password.reset', ['token' => $token]))->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nuevaClave123',
            'password_confirmation' => 'nuevaClave123',
        ]);

        $response->assertSessionHasErrors('email');

        $user->refresh();
        $this->assertSame($originalPassword, $user->password);
    }

    public function test_old_password_no_longer_works_after_reset(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nuevaClave123',
            'password_confirmation' => 'nuevaClave123',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_remember_token_is_regenerated_after_reset(): void
    {
        $user = User::factory()->create([
            'remember_token' => 'old-remember-token',
        ]);
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nuevaClave123',
            'password_confirmation' => 'nuevaClave123',
        ]);

        $user->refresh();
        $this->assertNotSame('old-remember-token', $user->remember_token);
        $this->assertNotEmpty($user->remember_token);
    }

    public function test_password_cannot_be_reset_with_password_shorter_than_minimum(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->from(route('password.reset', ['token' => $token]))->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'short1',
            'password_confirmation' => 'short1',
        ]);

        $response->assertSessionHasErrors('password');
    }
}
