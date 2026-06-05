<?php

namespace App\Services\Firebase;

use App\Contracts\Notifications\PushNotificationProvider;
use App\Models\FcmToken;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Throwable;

class FirebasePushNotificationAdapter implements PushNotificationProvider
{
    protected function getMessaging()
    {
        $credentials = config('services.firebase.credentials');

        $decoded = null;

        $base64Decoded = base64_decode($credentials, true);
        if ($base64Decoded !== false) {
            $jsonFromBase64 = json_decode($base64Decoded, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $decoded = $jsonFromBase64;
            }
        }

        if ($decoded === null) {
            $jsonDirect = json_decode($credentials, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $decoded = $jsonDirect;
            }
        }

        if ($decoded !== null) {
            $factory = (new Factory)->withServiceAccount($decoded);
        } else {
            $factory = (new Factory)->withServiceAccount(base_path($credentials));
        }

        return $factory->createMessaging();
    }

    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        $tokens = FcmToken::where('user_id', $user->id)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            Log::info('FCM: no hay tokens para el usuario', ['user_id' => $user->id]);

            return;
        }

        $this->sendToTokens($tokens, $title, $body, $data);
    }

    public function sendToRole(string $role, string $title, string $body, array $data = []): void
    {
        $users = User::role($role)->get();
        Log::info('FCM: enviando a rol', ['role' => $role, 'users_count' => $users->count()]);
        foreach ($users as $user) {
            $this->sendToUser($user, $title, $body, $data);
        }
    }

    public function sendToRoles(array $roles, string $title, string $body, array $data = []): void
    {
        foreach ($roles as $role) {
            $this->sendToRole($role, $title, $body, $data);
        }
    }

    private function sendToTokens(array $tokens, string $title, string $body, array $data = []): void
    {
        Log::info('FCM: enviando push', [
            'tokens' => array_map(fn ($t) => substr($t, 0, 20).'...', $tokens),
            'title' => $title,
        ]);

        try {
            $messaging = $this->getMessaging();

            // Solo data, sin notification — el SW maneja la notificación
            $payload = array_merge($data, [
                'title' => $title,
                'body' => $body,
            ]);

            foreach ($tokens as $token) {
                try {
                    $message = CloudMessage::withTarget('token', $token)
                        ->withData($payload);

                    $messaging->send($message);
                    Log::info('FCM: push enviado OK', ['token' => substr($token, 0, 20).'...']);
                } catch (Throwable $e) {
                    Log::warning('FCM token inválido o expirado, eliminando.', [
                        'token' => substr($token, 0, 20).'...',
                        'error' => $e->getMessage(),
                    ]);
                    FcmToken::where('token', $token)->delete();
                }
            }
        } catch (Throwable $e) {
            Log::error('Error enviando notificación FCM.', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
