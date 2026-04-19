<?php

use App\Services\Auth\SupabaseRoleSyncService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:sync-supabase-roles', function (SupabaseRoleSyncService $roleSyncService): void {
    $summary = $roleSyncService->syncAllUsersRoles();

    $this->info('Sincronizacion de roles finalizada.');
    $this->table(['Estado', 'Cantidad'], [
        ['synced', $summary['synced']],
        ['failed', $summary['failed']],
        ['skipped', $summary['skipped']],
    ]);
})->purpose('Sync app_role metadata to Supabase for all users');
