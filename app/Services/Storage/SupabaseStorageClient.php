<?php

namespace App\Services\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SupabaseStorageClient
{
    public function uploadUploadedFile(string $bucket, string $path, UploadedFile $file): void
    {
        $realPath = $file->getRealPath();
        if (! is_string($realPath) || $realPath === '') {
            throw new RuntimeException('No fue posible resolver el archivo temporal para upload.');
        }

        $contents = file_get_contents($realPath);
        if (! is_string($contents)) {
            throw new RuntimeException('No fue posible leer el archivo para upload.');
        }

        $mimeType = trim((string) $file->getMimeType());
        if ($mimeType === '') {
            $mimeType = 'application/octet-stream';
        }

        $this->uploadContents($bucket, $path, $contents, $mimeType);
    }

    public function uploadContents(
        string $bucket,
        string $path,
        string $contents,
        string $contentType = 'application/octet-stream',
    ): void {
        $normalizedPath = $this->normalizePath($path);

        if ($this->shouldUseLocalDiskForTesting()) {
            $stored = Storage::disk($this->testingDisk())->put($normalizedPath, $contents, ['visibility' => 'public']);
            if ($stored === false) {
                throw new RuntimeException('No fue posible almacenar el archivo en el disco de pruebas.');
            }

            return;
        }

        $this->assertRemoteConfiguration();

        $response = Http::timeout($this->timeoutSeconds())
            ->withHeaders([
                'apikey' => $this->serviceKey(),
                'Authorization' => 'Bearer '.$this->serviceKey(),
                'x-upsert' => 'true',
            ])
            ->withBody($contents, trim($contentType) !== '' ? $contentType : 'application/octet-stream')
            ->post($this->objectEndpoint($bucket, $normalizedPath));

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'Supabase Storage rechazo el upload (%d): %s',
                $response->status(),
                $response->body()
            ));
        }
    }

    public function deleteObject(string $bucket, string $path): void
    {
        $normalizedPath = $this->normalizePath($path);

        if ($this->shouldUseLocalDiskForTesting()) {
            $disk = Storage::disk($this->testingDisk());
            if ($disk->exists($normalizedPath)) {
                $disk->delete($normalizedPath);
            }

            return;
        }

        $this->assertRemoteConfiguration();

        $response = Http::timeout($this->timeoutSeconds())
            ->withHeaders([
                'apikey' => $this->serviceKey(),
                'Authorization' => 'Bearer '.$this->serviceKey(),
            ])
            ->delete($this->objectEndpoint($bucket, $normalizedPath));

        if ($response->status() === 404) {
            return;
        }

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'Supabase Storage rechazo el delete (%d): %s',
                $response->status(),
                $response->body()
            ));
        }
    }

    public function copyObject(string $bucket, string $sourcePath, string $targetPath): bool
    {
        $normalizedSource = $this->normalizePath($sourcePath);
        $normalizedTarget = $this->normalizePath($targetPath);

        if ($normalizedSource === $normalizedTarget) {
            return true;
        }

        if ($this->shouldUseLocalDiskForTesting()) {
            $disk = Storage::disk($this->testingDisk());
            if (! $disk->exists($normalizedSource)) {
                return false;
            }

            $contents = $disk->get($normalizedSource);
            if (! is_string($contents)) {
                throw new RuntimeException('No fue posible leer el objeto origen en el disco de pruebas.');
            }

            $stored = $disk->put($normalizedTarget, $contents, ['visibility' => 'public']);
            if ($stored === false) {
                throw new RuntimeException('No fue posible copiar el objeto en el disco de pruebas.');
            }

            return true;
        }

        $this->assertRemoteConfiguration();

        $downloadResponse = Http::timeout($this->timeoutSeconds())
            ->withHeaders([
                'apikey' => $this->serviceKey(),
                'Authorization' => 'Bearer '.$this->serviceKey(),
            ])
            ->get($this->objectEndpoint($bucket, $normalizedSource));

        if ($downloadResponse->status() === 404) {
            return false;
        }

        if (! $downloadResponse->successful()) {
            throw new RuntimeException(sprintf(
                'Supabase Storage rechazo la descarga para copia (%d): %s',
                $downloadResponse->status(),
                $downloadResponse->body()
            ));
        }

        $contentType = trim((string) $downloadResponse->header('Content-Type'));
        if ($contentType === '') {
            $contentType = 'application/octet-stream';
        }

        $this->uploadContents($bucket, $normalizedTarget, $downloadResponse->body(), $contentType);

        return true;
    }

    public function publicUrl(string $bucket, string $path): string
    {
        $normalizedPath = $this->normalizePath($path);

        return rtrim($this->publicBaseUrl(), '/')
            .'/storage/v1/object/public/'
            .rawurlencode(trim($bucket, '/'))
            .'/'
            .$this->encodePathSegments($normalizedPath);
    }

    private function shouldUseLocalDiskForTesting(): bool
    {
        if (! app()->environment('testing')) {
            return false;
        }

        return (bool) config('services.supabase.storage.use_local_disk_for_testing', true);
    }

    private function testingDisk(): string
    {
        $disk = trim((string) config('services.supabase.storage.testing_disk', 'public'));

        return $disk !== '' ? $disk : 'public';
    }

    private function assertRemoteConfiguration(): void
    {
        if ($this->apiBaseUrl() === '' || $this->serviceKey() === '') {
            throw new RuntimeException('Configuracion incompleta de Supabase Storage para operaciones remotas.');
        }
    }

    private function objectEndpoint(string $bucket, string $path): string
    {
        return rtrim($this->apiBaseUrl(), '/')
            .'/storage/v1/object/'
            .rawurlencode(trim($bucket, '/'))
            .'/'
            .$this->encodePathSegments($path);
    }

    private function normalizePath(string $path): string
    {
        $normalizedPath = trim(str_replace('\\', '/', $path), '/');

        if ($normalizedPath === '') {
            throw new RuntimeException('La ruta de storage no puede estar vacia.');
        }

        return $normalizedPath;
    }

    private function encodePathSegments(string $path): string
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn (string $part): bool => $part !== ''));

        return implode('/', array_map('rawurlencode', $segments));
    }

    private function apiBaseUrl(): string
    {
        return trim((string) config('services.supabase.storage.api_base_url', ''));
    }

    private function publicBaseUrl(): string
    {
        return trim((string) config('services.supabase.storage.public_base_url', ''));
    }

    private function serviceKey(): string
    {
        return trim((string) config('services.supabase.storage.service_key', ''));
    }

    private function timeoutSeconds(): int
    {
        return (int) config('services.supabase.storage.timeout', 10);
    }
}
