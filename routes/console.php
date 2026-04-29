<?php

use App\Models\Category;
use App\Models\Location;
use App\Models\TicketMedia;
use App\Models\User;
use App\Services\Ai\HuggingFaceService;
use App\Services\Auth\SupabaseRoleSyncService;
use App\Services\Storage\DomainStorageService;
use App\Services\Storage\SanitizedFileName;
use App\Services\Storage\SupabaseStorageClient;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

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

Artisan::command(
    'app:huggingface-ping {text? : Texto de prueba para generar embedding} {--model= : Sobrescribe el modelo de embeddings}',
    function (HuggingFaceService $huggingFace): int {
        $text = trim((string) ($this->argument('text') ?: 'Prueba de conectividad con Hugging Face para verificar token y respuesta.'));
        $model = trim((string) $this->option('model'));

        try {
            $vector = $huggingFace->embedding($text, $model !== '' ? $model : null);
        } catch (Throwable $exception) {
            $this->error('Hugging Face ping failed: '.$exception->getMessage());

            return Command::FAILURE;
        }

        $this->info('Hugging Face respondió correctamente.');
        $this->info('Modelo: '.($model !== '' ? $model : (string) config('ai.huggingface.embedding_model')));
        $this->info('Vector dimensions: '.count($vector));
        $this->line('Primeros valores: '.implode(', ', array_map(static fn (float $value): string => (string) $value, array_slice($vector, 0, 5))));

        return Command::SUCCESS;
    }
)->purpose('Ping real a Hugging Face para verificar token y respuesta');

Artisan::command(
    'app:migrate-storage-urls
        {--dry-run : Simula los cambios sin actualizar BD ni mover objetos}
        {--chunk=200 : Cantidad de registros por lote}
        {--delete-source : Elimina objeto origen despues de copiar al path nuevo}',
    function (DomainStorageService $domainStorage, SupabaseStorageClient $storageClient): void {
        $dryRun = (bool) $this->option('dry-run');
        $deleteSource = (bool) $this->option('delete-source');
        $chunkSize = max(1, (int) $this->option('chunk'));

        $summary = [
            'users' => ['processed' => 0, 'updated' => 0, 'copied' => 0, 'missing_source' => 0, 'skipped' => 0, 'errors' => 0],
            'categories' => ['processed' => 0, 'updated' => 0, 'copied' => 0, 'missing_source' => 0, 'skipped' => 0, 'errors' => 0],
            'tickets' => ['processed' => 0, 'updated' => 0, 'copied' => 0, 'missing_source' => 0, 'skipped' => 0, 'errors' => 0],
            'locations' => ['processed' => 0, 'updated' => 0, 'copied' => 0, 'missing_source' => 0, 'skipped' => 0, 'errors' => 0],
        ];

        $this->info($dryRun
            ? 'Iniciando migracion de URLs de storage en modo DRY RUN...'
            : 'Iniciando migracion de URLs de storage y backfill de objetos...');

        $extractRelativePath = function (string $rawValue): ?string {
            $value = trim($rawValue);
            if ($value === '' || str_starts_with($value, 'data:')) {
                return null;
            }

            $pathCandidate = $value;
            $parsedPath = parse_url($value, PHP_URL_PATH);
            if (is_string($parsedPath) && trim($parsedPath) !== '') {
                $pathCandidate = $parsedPath;
            }

            $normalizedPath = ltrim(str_replace('\\', '/', $pathCandidate), '/');
            if ($normalizedPath === '') {
                return null;
            }

            if (str_starts_with($normalizedPath, 'storage/v1/object/public/')) {
                $bucketAndPath = substr($normalizedPath, strlen('storage/v1/object/public/'));
                if (! is_string($bucketAndPath) || trim($bucketAndPath) === '') {
                    return null;
                }

                $parts = explode('/', $bucketAndPath, 2);
                if (! isset($parts[1]) || trim($parts[1]) === '') {
                    return null;
                }

                $segments = array_values(array_filter(explode('/', trim($parts[1], '/')), static fn (string $part): bool => $part !== ''));

                return $segments === [] ? null : implode('/', array_map('rawurldecode', $segments));
            }

            if (str_starts_with($normalizedPath, 'storage/')) {
                $legacyPath = trim(substr($normalizedPath, strlen('storage/')), '/');

                return $legacyPath !== '' ? $legacyPath : null;
            }

            if (! str_contains($normalizedPath, '/')) {
                return null;
            }

            $segments = array_values(array_filter(explode('/', trim($normalizedPath, '/')), static fn (string $part): bool => $part !== ''));

            return $segments === [] ? null : implode('/', array_map('rawurldecode', $segments));
        };

        $processRecords = function (
            string $domain,
            $query,
            string $column,
            callable $targetPathResolver
        ) use (
            $chunkSize,
            $domainStorage,
            $storageClient,
            $extractRelativePath,
            $dryRun,
            $deleteSource,
            &$summary,
        ): void {
            $bucket = $domainStorage->bucketName($domain);

            $query->orderBy('id')->chunk($chunkSize, function ($rows) use (
                $domain,
                $column,
                $targetPathResolver,
                $bucket,
                $storageClient,
                $extractRelativePath,
                $dryRun,
                $deleteSource,
                &$summary,
            ): void {
                foreach ($rows as $row) {
                    $summary[$domain]['processed']++;
                    $currentValue = trim((string) data_get($row, $column));

                    if ($currentValue === '') {
                        $summary[$domain]['skipped']++;

                        continue;
                    }

                    try {
                        $sourcePath = $extractRelativePath($currentValue);
                        if (! is_string($sourcePath) || $sourcePath === '') {
                            $summary[$domain]['skipped']++;

                            continue;
                        }

                        $targetPath = $targetPathResolver($row, $sourcePath);
                        if (! is_string($targetPath) || trim($targetPath) === '') {
                            $summary[$domain]['skipped']++;

                            continue;
                        }

                        $targetPath = trim($targetPath, '/');
                        $canonicalUrl = $storageClient->publicUrl($bucket, $targetPath);

                        if ($sourcePath !== $targetPath) {
                            if ($dryRun) {
                                $summary[$domain]['copied']++;
                            } else {
                                $copied = $storageClient->copyObject($bucket, $sourcePath, $targetPath);
                                if (! $copied) {
                                    $summary[$domain]['missing_source']++;

                                    continue;
                                }

                                $summary[$domain]['copied']++;

                                if ($deleteSource) {
                                    $storageClient->deleteObject($bucket, $sourcePath);
                                }
                            }
                        }

                        if ($currentValue !== $canonicalUrl) {
                            $summary[$domain]['updated']++;

                            if (! $dryRun) {
                                $row->{$column} = $canonicalUrl;
                                $row->save();
                            }
                        } else {
                            $summary[$domain]['skipped']++;
                        }
                    } catch (Throwable $exception) {
                        $summary[$domain]['errors']++;
                        $this->warn(sprintf(
                            '[%s] Error en registro %s: %s',
                            $domain,
                            (string) data_get($row, 'id', 'n/a'),
                            $exception->getMessage()
                        ));
                    }
                }
            });
        };

        $processRecords(
            'users',
            User::query()->whereRaw("avatar_url is not null and avatar_url <> ''", [], 'and'),
            'avatar_url',
            function (User $user, string $sourcePath) use ($domainStorage): string {
                $rawName = basename($sourcePath);
                $fileName = SanitizedFileName::fromRawName($rawName, 'avatar', 'png');

                return trim($domainStorage->pathPrefix('users'), '/').'/'.$user->id.'/'.$fileName;
            }
        );

        $processRecords(
            'categories',
            Category::query()->whereRaw("icon is not null and icon <> ''", [], 'and'),
            'icon',
            function (Category $category, string $sourcePath) use ($domainStorage): string {
                $rawName = basename($sourcePath);
                $fileName = SanitizedFileName::fromRawName($rawName, 'category-icon', 'png');

                return trim($domainStorage->pathPrefix('categories'), '/').'/'.$category->id.'/'.$fileName;
            }
        );

        $processRecords(
            'tickets',
            TicketMedia::query()->whereRaw("file_url is not null and file_url <> ''", [], 'and'),
            'file_url',
            function (TicketMedia $ticketMedia, string $sourcePath) use ($domainStorage): string {
                $rawName = basename($sourcePath);
                $fileName = SanitizedFileName::fromRawName($rawName, 'ticket-media', 'bin');

                return trim($domainStorage->pathPrefix('tickets'), '/').'/'.$ticketMedia->ticket_id.'/'.$fileName;
            }
        );

        $processRecords(
            'locations',
            Location::query()->whereRaw("qr_image_url is not null and qr_image_url <> ''", [], 'and'),
            'qr_image_url',
            function (Location $location, string $sourcePath) use ($domainStorage): string {
                $rawName = basename($sourcePath);
                $fallbackName = (string) $location->id.'.png';
                $fileName = SanitizedFileName::fromRawName($rawName !== '' ? $rawName : $fallbackName, (string) $location->id, 'png');

                return trim($domainStorage->pathPrefix('locations'), '/').'/'.$fileName;
            }
        );

        $this->table(
            ['Dominio', 'Procesados', 'Actualizados', 'Copiados', 'Origen faltante', 'Saltados', 'Errores'],
            [
                ['users', $summary['users']['processed'], $summary['users']['updated'], $summary['users']['copied'], $summary['users']['missing_source'], $summary['users']['skipped'], $summary['users']['errors']],
                ['categories', $summary['categories']['processed'], $summary['categories']['updated'], $summary['categories']['copied'], $summary['categories']['missing_source'], $summary['categories']['skipped'], $summary['categories']['errors']],
                ['tickets', $summary['tickets']['processed'], $summary['tickets']['updated'], $summary['tickets']['copied'], $summary['tickets']['missing_source'], $summary['tickets']['skipped'], $summary['tickets']['errors']],
                ['locations', $summary['locations']['processed'], $summary['locations']['updated'], $summary['locations']['copied'], $summary['locations']['missing_source'], $summary['locations']['skipped'], $summary['locations']['errors']],
            ]
        );

        $this->info($dryRun
            ? 'Dry run finalizado. No se persistieron cambios.'
            : 'Migracion de URLs de storage finalizada.');
    }
)->purpose('Migrate legacy media URLs to canonical Supabase public URLs and backfill objects');
