<?php

namespace App\Services\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

class DomainStorageService
{
    public function storeUploadedFile(
        string $domain,
        UploadedFile $file,
        string $pathPrefix,
        string $fileName,
    ): string {
        $diskName = $this->diskName($domain);
        $disk = Storage::disk($diskName);
        $normalizedPrefix = trim($pathPrefix, '/');
        $normalizedPath = $normalizedPrefix === ''
            ? $fileName
            : $normalizedPrefix . '/' . $fileName;

        $storedPath = $disk->putFileAs(
            $normalizedPrefix,
            $file,
            $fileName,
            ['visibility' => 'public']
        );

        if (! is_string($storedPath) || $storedPath === '') {
            throw new RuntimeException('No fue posible almacenar el archivo en el disco configurado.');
        }

        return $this->publicUrlForPath($diskName, $normalizedPath);
    }

    public function storeContents(
        string $domain,
        string $pathPrefix,
        string $fileName,
        string $contents,
    ): string {
        $diskName = $this->diskName($domain);
        $disk = Storage::disk($diskName);
        $normalizedPrefix = trim($pathPrefix, '/');
        $normalizedPath = $normalizedPrefix === ''
            ? $fileName
            : $normalizedPrefix . '/' . $fileName;

        $stored = $disk->put($normalizedPath, $contents, ['visibility' => 'public']);

        if ($stored === false) {
            throw new RuntimeException('No fue posible almacenar el contenido en el disco configurado.');
        }

        return $this->publicUrlForPath($diskName, $normalizedPath);
    }

    public function replaceUploadedFile(
        string $domain,
        ?string $previousUrl,
        UploadedFile $file,
        string $pathPrefix,
        string $fileName,
    ): string {
        $diskName = $this->diskName($domain);
        $newUrl = $this->storeUploadedFile($domain, $file, $pathPrefix, $fileName);

        $this->deletePreviousIfDifferentPath($diskName, $previousUrl, $newUrl);

        return $newUrl;
    }

    public function replaceContents(
        string $domain,
        ?string $previousUrl,
        string $pathPrefix,
        string $fileName,
        string $contents,
    ): string {
        $diskName = $this->diskName($domain);
        $newUrl = $this->storeContents($domain, $pathPrefix, $fileName, $contents);

        $this->deletePreviousIfDifferentPath($diskName, $previousUrl, $newUrl);

        return $newUrl;
    }

    public function deleteManagedUrl(string $domain, ?string $url): void
    {
        if (! is_string($url) || trim($url) === '') {
            return;
        }

        $diskName = $this->diskName($domain);
        $relativePath = $this->relativePathFromUrl($diskName, $url);

        if (! is_string($relativePath) || $relativePath === '') {
            return;
        }

        $disk = Storage::disk($diskName);
        if ($disk->exists($relativePath)) {
            $disk->delete($relativePath);
        }
    }

    public function pathPrefix(string $domain): string
    {
        $prefix = config('filesystems.domain_prefixes.' . $domain);

        if (! is_string($prefix)) {
            throw new InvalidArgumentException('No existe prefijo configurado para el dominio de storage: ' . $domain);
        }

        return trim($prefix, '/');
    }

    public function diskName(string $domain): string
    {
        $diskName = config('filesystems.domain_disks.' . $domain);

        if (! is_string($diskName) || trim($diskName) === '') {
            throw new InvalidArgumentException('No existe disco configurado para el dominio de storage: ' . $domain);
        }

        return trim($diskName);
    }

    private function publicUrlForPath(string $diskName, string $normalizedPath): string
    {
        $configuredDiskUrl = trim((string) config('filesystems.disks.' . $diskName . '.url'));
        if ($configuredDiskUrl !== '') {
            return rtrim($configuredDiskUrl, '/') . '/' . ltrim($normalizedPath, '/');
        }

        $driver = (string) config('filesystems.disks.' . $diskName . '.driver');
        if ($driver === 's3') {
            $bucket = trim((string) config('filesystems.disks.' . $diskName . '.bucket'), '/');
            $endpoint = trim((string) config('filesystems.disks.' . $diskName . '.endpoint'));
            $supabaseBaseUrl = $this->supabaseBaseUrlFromS3Endpoint($endpoint);

            if ($supabaseBaseUrl !== null && $bucket !== '') {
                return $supabaseBaseUrl
                    . '/storage/v1/object/public/'
                    . $bucket
                    . '/'
                    . ltrim($normalizedPath, '/');
            }
        }

        return Storage::disk($diskName)->url($normalizedPath);
    }

    private function supabaseBaseUrlFromS3Endpoint(string $endpoint): ?string
    {
        if ($endpoint === '') {
            return null;
        }

        $parts = parse_url($endpoint);
        if (! is_array($parts)) {
            return null;
        }

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;
        $path = $parts['path'] ?? '';

        if (! is_string($scheme) || ! is_string($host) || ! is_string($path)) {
            return null;
        }

        if (! str_ends_with($host, '.storage.supabase.co')) {
            return null;
        }

        if (! str_starts_with($path, '/storage/v1/s3')) {
            return null;
        }

        $baseHost = substr($host, 0, -strlen('.storage.supabase.co')) . '.supabase.co';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $scheme . '://' . $baseHost . $port;
    }

    private function deletePreviousIfDifferentPath(string $diskName, ?string $previousUrl, string $newUrl): void
    {
        $previousPath = $this->relativePathFromUrl($diskName, (string) $previousUrl);
        $newPath = $this->relativePathFromUrl($diskName, $newUrl);

        if (
            is_string($previousPath)
            && $previousPath !== ''
            && $previousPath !== $newPath
        ) {
            $disk = Storage::disk($diskName);
            if ($disk->exists($previousPath)) {
                $disk->delete($previousPath);
            }
        }
    }

    private function relativePathFromUrl(string $diskName, string $url): ?string
    {
        $normalizedUrl = trim($url);
        $configuredDiskUrl = rtrim((string) config('filesystems.disks.' . $diskName . '.url'), '/');

        if ($configuredDiskUrl !== '' && str_starts_with($normalizedUrl, $configuredDiskUrl . '/')) {
            return ltrim(substr($normalizedUrl, strlen($configuredDiskUrl)), '/');
        }

        $parsedPath = parse_url($normalizedUrl, PHP_URL_PATH);
        if (! is_string($parsedPath) || trim($parsedPath) === '') {
            return null;
        }

        $normalizedPath = ltrim(trim($parsedPath), '/');
        if ($normalizedPath === '') {
            return null;
        }

        if (str_starts_with($normalizedPath, 'storage/')) {
            return substr($normalizedPath, strlen('storage/'));
        }

        if (str_contains($normalizedPath, 'object/public/')) {
            $parts = explode('object/public/', $normalizedPath, 2);
            $bucketAndPath = $parts[1] ?? '';
            $bucket = trim((string) config('filesystems.disks.' . $diskName . '.bucket'), '/');

            if ($bucket !== '' && str_starts_with($bucketAndPath, $bucket . '/')) {
                return substr($bucketAndPath, strlen($bucket) + 1);
            }
        }

        return null;
    }
}
