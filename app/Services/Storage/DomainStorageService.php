<?php

namespace App\Services\Storage;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class DomainStorageService
{
    public function __construct(private SupabaseStorageClient $storageClient) {}

    public function storeUploadedFile(
        string $domain,
        UploadedFile $file,
        string $pathPrefix,
        string $fileName,
    ): string {
        $bucketName = $this->bucketName($domain);
        $normalizedPrefix = trim($pathPrefix, '/');
        $normalizedPath = $normalizedPrefix === ''
            ? $fileName
            : $normalizedPrefix.'/'.$fileName;

        $this->storageClient->uploadUploadedFile($bucketName, $normalizedPath, $file);

        return $this->storageClient->publicUrl($bucketName, $normalizedPath);
    }

    public function storeContents(
        string $domain,
        string $pathPrefix,
        string $fileName,
        string $contents,
        string $contentType = 'application/octet-stream',
    ): string {
        $bucketName = $this->bucketName($domain);
        $normalizedPrefix = trim($pathPrefix, '/');
        $normalizedPath = $normalizedPrefix === ''
            ? $fileName
            : $normalizedPrefix.'/'.$fileName;

        $this->storageClient->uploadContents($bucketName, $normalizedPath, $contents, $contentType);

        return $this->storageClient->publicUrl($bucketName, $normalizedPath);
    }

    public function replaceUploadedFile(
        string $domain,
        ?string $previousUrl,
        UploadedFile $file,
        string $pathPrefix,
        string $fileName,
    ): string {
        $bucketName = $this->bucketName($domain);
        $newUrl = $this->storeUploadedFile($domain, $file, $pathPrefix, $fileName);

        $this->deletePreviousIfDifferentPath($bucketName, $previousUrl, $newUrl);

        return $newUrl;
    }

    public function replaceContents(
        string $domain,
        ?string $previousUrl,
        string $pathPrefix,
        string $fileName,
        string $contents,
        string $contentType = 'application/octet-stream',
    ): string {
        $bucketName = $this->bucketName($domain);
        $newUrl = $this->storeContents($domain, $pathPrefix, $fileName, $contents, $contentType);

        $this->deletePreviousIfDifferentPath($bucketName, $previousUrl, $newUrl);

        return $newUrl;
    }

    public function deleteManagedUrl(string $domain, ?string $url): void
    {
        if (! is_string($url) || trim($url) === '') {
            return;
        }

        $bucketName = $this->bucketName($domain);
        $relativePath = $this->relativePathFromUrl($bucketName, $url);

        if (! is_string($relativePath) || $relativePath === '') {
            return;
        }

        $this->storageClient->deleteObject($bucketName, $relativePath);
    }

    public function pathPrefix(string $domain): string
    {
        $prefix = config('services.supabase.storage.domain_prefixes.'.$domain);

        if (! is_string($prefix) || trim($prefix) === '') {
            $prefix = config('filesystems.domain_prefixes.'.$domain);
        }

        if (! is_string($prefix)) {
            throw new InvalidArgumentException('No existe prefijo configurado para el dominio de storage: '.$domain);
        }

        return trim($prefix, '/');
    }

    public function bucketName(string $domain): string
    {
        $bucketName = config('services.supabase.storage.domain_buckets.'.$domain);

        if (! is_string($bucketName) || trim($bucketName) === '') {
            throw new InvalidArgumentException('No existe bucket configurado para el dominio de storage: '.$domain);
        }

        return trim($bucketName);
    }

    private function deletePreviousIfDifferentPath(string $bucketName, ?string $previousUrl, string $newUrl): void
    {
        $previousPath = $this->relativePathFromUrl($bucketName, (string) $previousUrl);
        $newPath = $this->relativePathFromUrl($bucketName, $newUrl);

        if (
            is_string($previousPath)
            && $previousPath !== ''
            && $previousPath !== $newPath
        ) {
            $this->storageClient->deleteObject($bucketName, $previousPath);
        }
    }

    private function relativePathFromUrl(string $bucketName, string $url): ?string
    {
        $normalizedUrl = trim($url);

        $parsedPath = parse_url($normalizedUrl, PHP_URL_PATH);
        if (! is_string($parsedPath) || trim($parsedPath) === '') {
            return null;
        }

        $normalizedPath = ltrim(trim($parsedPath), '/');
        if ($normalizedPath === '') {
            return null;
        }

        if (str_starts_with($normalizedPath, 'storage/v1/object/public/')) {
            $bucketAndPath = substr($normalizedPath, strlen('storage/v1/object/public/'));
            if (! is_string($bucketAndPath) || trim($bucketAndPath) === '') {
                return null;
            }

            $parts = explode('/', $bucketAndPath, 2);
            $encodedBucket = $parts[0] ?? '';
            $encodedObjectPath = $parts[1] ?? '';

            if (rawurldecode($encodedBucket) !== $bucketName || trim($encodedObjectPath) === '') {
                return null;
            }

            return $this->decodePathSegments($encodedObjectPath);
        }

        if (str_starts_with($normalizedPath, 'storage/')) {
            return substr($normalizedPath, strlen('storage/'));
        }

        return null;
    }

    private function decodePathSegments(string $path): string
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn (string $part): bool => $part !== ''));

        if ($segments === []) {
            return '';
        }

        return implode('/', array_map('rawurldecode', $segments));
    }
}
