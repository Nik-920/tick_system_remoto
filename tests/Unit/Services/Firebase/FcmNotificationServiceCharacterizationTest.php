<?php

namespace Tests\Unit\Services\Firebase;

use App\Models\FcmToken;
use App\Models\User;
use App\Services\Firebase\FcmNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use RuntimeException;
use Tests\TestCase;

/**
 * Tests de caracterización: documentan el comportamiento actual de FcmNotificationService
 * ANTES del refactor. Sirven como red de seguridad.
 *
 * Estrategia: subclase que solo sobreescribe getMessaging() para inyectar un stub.
 * Toda la lógica real de sendToUser/sendToTokens se ejecuta sin modificar.
 */
class FcmNotificationServiceCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(Messaging $stub): FcmNotificationService
    {
        return new class($stub) extends FcmNotificationService
        {
            public function __construct(private Messaging $stub) {}

            protected function getMessaging(): Messaging
            {
                return $this->stub;
            }
        };
    }

    // ──────────────────────────────────────────────
    // Sin tokens: log y salida temprana sin llamar a Firebase
    // ──────────────────────────────────────────────

    public function test_send_to_user_with_no_tokens_logs_and_returns(): void
    {
        Log::spy();

        $messaging = $this->createMock(Messaging::class);
        $messaging->expects($this->never())->method('send');

        $user = User::factory()->create();

        $this->makeService($messaging)->sendToUser($user, 'Título', 'Cuerpo');

        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('info')
            ->withArgs(fn ($msg) => str_contains((string) $msg, 'no hay tokens'));

        $this->addToAssertionCount(1);
    }

    // ──────────────────────────────────────────────
    // Con N tokens: messaging->send() se llama N veces
    // ──────────────────────────────────────────────

    public function test_send_to_user_calls_messaging_send_per_token(): void
    {
        Log::spy();

        $messaging = $this->createMock(Messaging::class);
        $messaging->expects($this->exactly(2))->method('send');

        $user = User::factory()->create();
        FcmToken::factory()->count(2)->create(['user_id' => $user->id]);

        $this->makeService($messaging)->sendToUser($user, 'Título', 'Cuerpo');

        $this->addToAssertionCount(1);
    }

    // ──────────────────────────────────────────────
    // Token inválido: se elimina de fcm_tokens
    // ──────────────────────────────────────────────

    public function test_invalid_token_triggers_delete_from_fcm_tokens_table(): void
    {
        Log::spy();

        $messaging = $this->createMock(Messaging::class);
        $messaging->method('send')
            ->willThrowException(new RuntimeException('Token not registered'));

        $user = User::factory()->create();
        $token = FcmToken::factory()->create(['user_id' => $user->id]);

        $this->makeService($messaging)->sendToUser($user, 'Título', 'Cuerpo');

        $this->assertDatabaseMissing('fcm_tokens', ['id' => $token->id]);
    }

    // ──────────────────────────────────────────────
    // getMessaging lanza excepción → se captura y loguea, no propaga
    // ──────────────────────────────────────────────

    public function test_outer_exception_in_get_messaging_is_caught_and_logged(): void
    {
        Log::spy();

        $user = User::factory()->create();
        FcmToken::factory()->create(['user_id' => $user->id]);

        $service = new class extends FcmNotificationService
        {
            protected function getMessaging(): never
            {
                throw new RuntimeException('Firebase credentials not found');
            }
        };

        $service->sendToUser($user, 'Título', 'Cuerpo');

        /** @phpstan-ignore-next-line */
        Log::shouldHaveReceived('error')
            ->withArgs(fn ($msg) => str_contains((string) $msg, 'Error enviando'));

        $this->addToAssertionCount(1);
    }
}
