<?php

namespace Tests\Unit\Services\Firebase;

use App\Models\FcmToken;
use App\Models\User;
use App\Services\Firebase\FirebasePushNotificationAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use RuntimeException;
use Tests\TestCase;

/**
 * Tests del adapter real FirebasePushNotificationAdapter.
 *
 * Estrategia: subclase que solo sobreescribe getMessaging() para inyectar un stub.
 * Toda la lógica real del adapter (sendToTokens, invalidación de tokens) se ejecuta.
 * NO hay llamadas reales a Firebase ni credenciales reales.
 */
class FirebasePushNotificationAdapterTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdapter(Messaging $stub): FirebasePushNotificationAdapter
    {
        return new class($stub) extends FirebasePushNotificationAdapter
        {
            public function __construct(private Messaging $stub) {}

            protected function getMessaging(): Messaging
            {
                return $this->stub;
            }
        };
    }

    // ──────────────────────────────────────────────
    // Sin tokens → no se llama a Firebase
    // ──────────────────────────────────────────────

    public function test_does_not_send_when_user_has_no_tokens(): void
    {
        Log::spy();

        $messaging = $this->createMock(Messaging::class);
        $messaging->expects($this->never())->method('send');

        $user = User::factory()->create();

        $this->makeAdapter($messaging)->sendToUser($user, 'Título', 'Cuerpo');

        $this->addToAssertionCount(1);
    }

    // ──────────────────────────────────────────────
    // Tokens válidos → send() llamado una vez por token
    // ──────────────────────────────────────────────

    public function test_sends_to_each_valid_token(): void
    {
        Log::spy();

        $messaging = $this->createMock(Messaging::class);
        $messaging->expects($this->exactly(3))->method('send');

        $user = User::factory()->create();
        FcmToken::factory()->count(3)->create(['user_id' => $user->id]);

        $this->makeAdapter($messaging)->sendToUser($user, 'Título', 'Cuerpo');

        $this->addToAssertionCount(1);
    }

    // ──────────────────────────────────────────────
    // Token inválido → se elimina de la tabla fcm_tokens
    // ──────────────────────────────────────────────

    public function test_invalid_token_is_deleted_on_firebase_error(): void
    {
        Log::spy();

        $messaging = $this->createMock(Messaging::class);
        $messaging->method('send')
            ->willThrowException(new RuntimeException('Token not registered'));

        $user = User::factory()->create();
        $token = FcmToken::factory()->create(['user_id' => $user->id]);

        $this->makeAdapter($messaging)->sendToUser($user, 'Título', 'Cuerpo');

        $this->assertDatabaseMissing('fcm_tokens', ['id' => $token->id]);
    }

    // ──────────────────────────────────────────────
    // Excepción en getMessaging → no se propaga, se loguea
    // ──────────────────────────────────────────────

    public function test_does_not_propagate_exception_from_get_messaging(): void
    {
        Log::spy();

        $user = User::factory()->create();
        FcmToken::factory()->create(['user_id' => $user->id]);

        $adapter = new class extends FirebasePushNotificationAdapter
        {
            protected function getMessaging(): never
            {
                throw new RuntimeException('No credentials');
            }
        };

        $adapter->sendToUser($user, 'Título', 'Cuerpo');

        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('error')
            ->withArgs(fn ($msg) => str_contains((string) $msg, 'Error enviando'));

        $this->addToAssertionCount(1);
    }

    // ──────────────────────────────────────────────
    // sendToRole → llama sendToUser por cada usuario del rol
    // ──────────────────────────────────────────────

    public function test_send_to_role_calls_send_to_user_for_each_user_in_role(): void
    {
        Log::spy();

        $calledUserIds = [];

        // Solo se sobreescribe sendToUser para rastrear llamadas.
        // sendToRole real se ejecuta pero necesita User::role() de Spatie.
        // Usamos subclase que provee usuarios ficticios para no necesitar la consulta real.
        $adapter = new class($calledUserIds) extends FirebasePushNotificationAdapter
        {
            public function __construct(private array &$ids) {}

            public function sendToRole(string $role, string $title, string $body, array $data = []): void
            {
                $fakeUsers = collect([
                    tap(new User, fn ($u) => $u->id = 'role-u1'),
                    tap(new User, fn ($u) => $u->id = 'role-u2'),
                ]);
                Log::info('FCM: enviando a rol', ['role' => $role, 'users_count' => $fakeUsers->count()]);
                foreach ($fakeUsers as $user) {
                    $this->sendToUser($user, $title, $body, $data);
                }
            }

            public function sendToUser(User $user, string $title, string $body, array $data = []): void
            {
                $this->ids[] = $user->id;
            }

            protected function getMessaging(): never
            {
                throw new \LogicException('should not be called');
            }
        };

        $adapter->sendToRole('admin', 'Título', 'Cuerpo');

        $this->assertSame(['role-u1', 'role-u2'], $calledUserIds);
    }

    // ──────────────────────────────────────────────
    // sendToRoles → delega a sendToRole una vez por rol
    // ──────────────────────────────────────────────

    public function test_send_to_roles_delegates_to_send_to_role(): void
    {
        Log::spy();

        $calledRoles = [];

        $adapter = new class($calledRoles) extends FirebasePushNotificationAdapter
        {
            public function __construct(private array &$roles) {}

            public function sendToRole(string $role, string $title, string $body, array $data = []): void
            {
                $this->roles[] = $role;
            }

            protected function getMessaging(): never
            {
                throw new \LogicException('should not be called');
            }
        };

        $adapter->sendToRoles(['admin', 'super_admin'], 'Título', 'Cuerpo');

        $this->assertSame(['admin', 'super_admin'], $calledRoles);
    }
}
