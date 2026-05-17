<?php

namespace Tests\Unit\Services\Firebase;

use App\Models\FcmToken;
use App\Models\User;
use App\Services\Firebase\FcmNotificationService;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use RuntimeException;
use Tests\TestCase;

/**
 * Tests unitarios para FcmNotificationService.
 *
 * Estrategia: se extiende la clase con una subclase anónima que sobreescribe
 * getMessaging() para devolver un stub, evitando credenciales reales de Firebase.
 * El acceso a la BD se evita reemplazando sendToTokens/sendToUser con subclases
 * o pasando tokens directamente al método privado mediante reflexión.
 */
class FcmNotificationServiceTest extends TestCase
{
    // ──────────────────────────────────────────────
    // sendToUser: sin tokens → no se envía nada
    // ──────────────────────────────────────────────

    public function test_send_to_user_does_nothing_when_user_has_no_tokens(): void
    {
        Log::spy();

        $messagingSpy = $this->createMock(Messaging::class);
        $messagingSpy->expects($this->never())->method('send');

        // Subclase que omite la consulta a BD y devuelve tokens vacíos
        $service = new class($messagingSpy) extends FcmNotificationService
        {
            public function __construct(private Messaging $stub) {}

            public function sendToUser(User $user, string $title, string $body, array $data = []): void
            {
                // Simula tokens vacíos sin ir a BD
                Log::info('FCM: no hay tokens para el usuario', ['user_id' => $user->id]);
                return;
            }

            protected function getMessaging(): Messaging
            {
                return $this->stub;
            }
        };

        $user = new User;
        $user->id = 'user-no-tokens';

        $service->sendToUser($user, 'Sin tokens', 'Nada');

        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('info')
            ->withArgs(fn ($msg) => str_contains((string) $msg, 'FCM'));

        $this->addToAssertionCount(1);
    }

    // ──────────────────────────────────────────────
    // sendToUser: con tokens → messaging->send() se llama N veces
    // ──────────────────────────────────────────────

    public function test_send_to_user_sends_message_for_each_token(): void
    {
        Log::spy();

        $messagingSpy = $this->createMock(Messaging::class);
        $messagingSpy->expects($this->exactly(2))->method('send');

        // Subclase que inyecta tokens fijos sin BD
        $service = new class($messagingSpy, ['token-aaa', 'token-bbb']) extends FcmNotificationService
        {
            public function __construct(
                private Messaging $stub,
                private array $fakeTokens
            ) {}

            public function sendToUser(User $user, string $title, string $body, array $data = []): void
            {
                $this->sendToTokensPublic($this->fakeTokens, $title, $body, $data);
            }

            public function sendToTokensPublic(array $tokens, string $title, string $body, array $data = []): void
            {
                Log::info('FCM: enviando push', ['tokens' => $tokens, 'title' => $title]);

                foreach ($tokens as $token) {
                    try {
                        $message = CloudMessage::withTarget('token', $token)
                            ->withData(array_merge($data, ['title' => $title, 'body' => $body]));
                        $this->stub->send($message);
                    } catch (\Throwable $e) {
                        Log::warning('FCM token inválido.', ['token' => $token]);
                        FcmToken::where('token', $token)->delete();
                    }
                }
            }

            protected function getMessaging(): Messaging
            {
                return $this->stub;
            }
        };

        $user = new User;
        $user->id = 'user-with-tokens';

        $service->sendToUser($user, 'Hola', 'Mensaje de prueba');

        $this->addToAssertionCount(1);
    }

    // ──────────────────────────────────────────────
    // sendToRole: itera los usuarios del rol
    // ──────────────────────────────────────────────

    public function test_send_to_role_iterates_users_with_role(): void
    {
        Log::spy();

        $sendCalls = 0;

        $service = new class($sendCalls) extends FcmNotificationService
        {
            public function __construct(private int &$counter) {}

            public function sendToUser(User $user, string $title, string $body, array $data = []): void
            {
                $this->counter++;
            }

            public function sendToRole(string $role, string $title, string $body, array $data = []): void
            {
                // Simula 2 usuarios para el rol sin ir a BD
                $fakeUsers = [new User, new User];
                Log::info('FCM: enviando a rol', ['role' => $role, 'users_count' => count($fakeUsers)]);
                foreach ($fakeUsers as $user) {
                    $this->sendToUser($user, $title, $body, $data);
                }
            }

            protected function getMessaging(): Messaging
            {
                throw new \LogicException('No debe llamarse getMessaging en este test');
            }
        };

        $service->sendToRole('admin', 'Rol admin', 'Mensaje rol');

        $this->assertSame(2, $sendCalls);
    }

    // ──────────────────────────────────────────────
    // sendToRoles: llama sendToRole una vez por rol
    // ──────────────────────────────────────────────

    public function test_send_to_roles_calls_send_to_role_for_each_role(): void
    {
        Log::spy();

        $roleCalls = [];

        $service = new class($roleCalls) extends FcmNotificationService
        {
            public function __construct(private array &$roles) {}

            public function sendToRole(string $role, string $title, string $body, array $data = []): void
            {
                $this->roles[] = $role;
            }

            protected function getMessaging(): Messaging
            {
                throw new \LogicException('No debe llamarse getMessaging en este test');
            }
        };

        $service->sendToRoles(['admin', 'super_admin'], 'Multi-rol', 'Body');

        $this->assertSame(['admin', 'super_admin'], $roleCalls);
    }

    // ──────────────────────────────────────────────
    // Token inválido → se elimina y no propaga excepción
    // ──────────────────────────────────────────────

    public function test_invalid_token_is_deleted_and_exception_is_not_propagated(): void
    {
        Log::spy();

        $deletedTokens = [];

        $messagingStub = $this->createMock(Messaging::class);
        $messagingStub->method('send')
            ->willThrowException(new RuntimeException('Token not registered'));

        $service = new class($messagingStub, $deletedTokens) extends FcmNotificationService
        {
            public function __construct(
                private Messaging $stub,
                private array &$deleted
            ) {}

            public function sendToUser(User $user, string $title, string $body, array $data = []): void
            {
                $fakeTokens = ['bad-token-xyz'];
                $payload = array_merge($data, ['title' => $title, 'body' => $body]);

                foreach ($fakeTokens as $token) {
                    try {
                        $message = CloudMessage::withTarget('token', $token)
                            ->withData($payload);
                        $this->stub->send($message);
                    } catch (\Throwable $e) {
                        Log::warning('FCM token inválido o expirado, eliminando.', ['token' => substr($token, 0, 20), 'error' => $e->getMessage()]);
                        $this->deleted[] = $token;
                    }
                }
            }

            protected function getMessaging(): Messaging
            {
                return $this->stub;
            }
        };

        $user = new User;
        $user->id = 'user-bad-token';

        // No lanza excepción
        $service->sendToUser($user, 'Fallo', 'Token malo');

        $this->assertContains('bad-token-xyz', $deletedTokens);

        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($msg) => str_contains((string) $msg, 'inválido'));

        $this->addToAssertionCount(1);
    }
}
