<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SupabaseRoleSyncService
{
    /**
     * @return array{status:string,message:string,role:string}
     */
    public function syncUserRole(User $user, ?string $role = null): array
    {
        $resolvedRole = $role !== null && $role !== '' ? $role : $this->resolvePrimaryRole($user);

        if (! $this->isEnabled()) {
            return [
                'status' => 'skipped',
                'message' => 'Sincronizacion de roles con Supabase deshabilitada.',
                'role' => $resolvedRole,
            ];
        }

        $baseUrl = trim((string) config('services.supabase.url', ''));
        $serviceRoleKey = trim((string) config('services.supabase.service_role_key', ''));

        if ($baseUrl === '' || $serviceRoleKey === '') {
            return [
                'status' => 'skipped',
                'message' => 'Configuracion incompleta de Supabase para sincronizacion de roles.',
                'role' => $resolvedRole,
            ];
        }

        try {
            $response = Http::timeout($this->timeoutSeconds())
                ->acceptJson()
                ->withHeaders([
                    'apikey' => $serviceRoleKey,
                    'Authorization' => 'Bearer '.$serviceRoleKey,
                ])
                ->put($this->buildUserAdminEndpoint($baseUrl, $user->id), [
                    'app_metadata' => [
                        'app_role' => $resolvedRole,
                    ],
                ]);

            if ($response->successful()) {
                return [
                    'status' => 'synced',
                    'message' => 'Rol sincronizado correctamente con Supabase.',
                    'role' => $resolvedRole,
                ];
            }

            Log::warning('supabase.role_sync.failed', [
                'user_id' => $user->id,
                'role' => $resolvedRole,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return [
                'status' => 'failed',
                'message' => 'No se pudo sincronizar el rol con Supabase.',
                'role' => $resolvedRole,
            ];
        } catch (Throwable $exception) {
            report($exception);

            Log::warning('supabase.role_sync.exception', [
                'user_id' => $user->id,
                'role' => $resolvedRole,
                'exception' => $exception->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'message' => 'Error inesperado al sincronizar rol con Supabase.',
                'role' => $resolvedRole,
            ];
        }
    }

    /**
     * @return array{synced:int,failed:int,skipped:int}
     */
    public function syncAllUsersRoles(): array
    {
        $summary = [
            'synced' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        User::query()
            ->with('roles')
            ->orderBy('created_at')
            ->get()
            ->each(function (User $user) use (&$summary): void {
                $result = $this->syncUserRole($user);
                $status = $result['status'];

                if (array_key_exists($status, $summary)) {
                    $summary[$status]++;
                }
            });

        return $summary;
    }

    private function resolvePrimaryRole(User $user): string
    {
        if (! method_exists($user, 'getRoleNames')) {
            return 'reporter';
        }

        $role = $user->getRoleNames()->first();

        return is_string($role) && $role !== '' ? $role : 'reporter';
    }

    private function isEnabled(): bool
    {
        return (bool) config('services.supabase.role_sync_enabled', false);
    }

    private function timeoutSeconds(): int
    {
        return (int) config('services.supabase.timeout', 10);
    }

    private function buildUserAdminEndpoint(string $baseUrl, string $userId): string
    {
        $basePath = trim((string) config('services.supabase.auth_admin_users_endpoint', '/auth/v1/admin/users'));

        return rtrim($baseUrl, '/').'/'.trim($basePath, '/').'/'.rawurlencode($userId);
    }
}
