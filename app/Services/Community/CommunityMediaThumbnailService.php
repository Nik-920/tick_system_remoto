<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Models\TicketMedia;
use Illuminate\Support\Facades\Storage;

/**
 * Generates and caches JPEG thumbnails for Community feed media.
 *
 * Cache strategy (Opción A — no new table):
 * - Thumbnails are stored at:
 *     {thumbnail_path}/{media_id}/thumb_{hash}_{W}x{H}.jpg
 * - Hash is derived from file_url + created_at timestamp.
 * - Cache is hit on every request before generating a new thumbnail.
 * - Visibility gates (canViewInCommunity) are enforced by the controller
 *   BEFORE this service is called, so cached thumbnails are never served
 *   for hidden/cancelled/rejected tickets.
 *
 * Image processing:
 * - Uses native PHP GD (ext-gd); falls back to null if unavailable.
 * - Output: JPEG quality 82, max 640×360, aspect ratio preserved.
 * - PNG transparency is flattened to white before JPEG encoding.
 */
final class CommunityMediaThumbnailService
{
    public function __construct(
        private readonly CommunityRemoteMediaFetcher $remoteFetcher,
    ) {}

    public function thumbnailFor(TicketMedia $media): ?CommunityThumbnail
    {
        $disk = (string) config('community.media.thumbnail_disk', 'public');
        $cachePath = $this->cachePathFor($media);

        if (Storage::disk($disk)->exists($cachePath)) {
            return new CommunityThumbnail(disk: $disk, path: $cachePath, contentType: 'image/jpeg');
        }

        $original = $this->getOriginalBytes($media);
        if ($original === null) {
            return null;
        }

        [$bytes] = $original;

        $thumbBytes = $this->generateJpegThumbnail($bytes);
        if ($thumbBytes === null) {
            return null;
        }

        Storage::disk($disk)->put($cachePath, $thumbBytes);

        return new CommunityThumbnail(disk: $disk, path: $cachePath, contentType: 'image/jpeg');
    }

    public function cachePathFor(TicketMedia $media): string
    {
        $hash = substr(
            md5((string) ($media->file_url ?? '').(string) ($media->created_at?->timestamp ?? '0')),
            0,
            12,
        );
        $w = (int) config('community.media.thumbnail_width', 640);
        $h = (int) config('community.media.thumbnail_height', 360);
        $base = (string) config('community.media.thumbnail_path', 'community-thumbnails');

        return $base.'/'.$media->id.'/thumb_'.$hash.'_'.$w.'x'.$h.'.jpg';
    }

    public function purgeFor(TicketMedia $media): void
    {
        $disk = (string) config('community.media.thumbnail_disk', 'public');
        $base = (string) config('community.media.thumbnail_path', 'community-thumbnails');
        $dir = $base.'/'.$media->id;

        foreach (Storage::disk($disk)->files($dir) as $file) {
            Storage::disk($disk)->delete($file);
        }
    }

    /**
     * @return array{0: string, 1: string}|null [bytes, mimeType]
     */
    private function getOriginalBytes(TicketMedia $media): ?array
    {
        $fileUrl = trim((string) ($media->file_url ?? ''));
        if ($fileUrl === '') {
            return null;
        }

        $localPath = $this->extractStoragePath($fileUrl);
        if ($localPath !== null && Storage::disk('public')->exists($localPath)) {
            $bytes = Storage::disk('public')->get($localPath);
            if (is_string($bytes) && $bytes !== '') {
                $fileType = (string) ($media->file_type ?? '');
                $mime = str_contains($fileType, '/') ? $fileType : 'image/jpeg';

                return [$bytes, $mime];
            }
        }

        $payload = $this->remoteFetcher->fetch($media);
        if ($payload !== null) {
            return [$payload->bytes, $payload->contentType];
        }

        return null;
    }

    /**
     * Extracts a relative storage path from any supported file_url shape:
     *   - Supabase: https://xxx/storage/v1/object/public/{bucket}/{path} → {path}
     *   - Local:    http://localhost/storage/{path} → {path}
     */
    private function extractStoragePath(string $fileUrl): ?string
    {
        $parsedPath = parse_url($fileUrl, PHP_URL_PATH);
        if (! is_string($parsedPath)) {
            return null;
        }

        $normalized = ltrim(trim($parsedPath), '/');

        if (str_starts_with($normalized, 'storage/v1/object/public/')) {
            $afterPublic = substr($normalized, strlen('storage/v1/object/public/'));
            $slashPos = strpos($afterPublic, '/');
            if ($slashPos === false) {
                return null;
            }

            $encodedPath = substr($afterPublic, $slashPos + 1);
            if ($encodedPath === '') {
                return null;
            }

            $segments = array_values(array_filter(
                explode('/', $encodedPath),
                fn (string $p): bool => $p !== '',
            ));

            return implode('/', array_map('rawurldecode', $segments));
        }

        if (str_starts_with($normalized, 'storage/')) {
            return substr($normalized, strlen('storage/'));
        }

        return null;
    }

    private function generateJpegThumbnail(string $bytes): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            return null;
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);

        if ($srcW <= 0 || $srcH <= 0) {
            imagedestroy($src);

            return null;
        }

        $maxW = (int) config('community.media.thumbnail_width', 640);
        $maxH = (int) config('community.media.thumbnail_height', 360);
        [$dstW, $dstH] = $this->fitSize($srcW, $srcH, $maxW, $maxH);

        $dst = imagecreatetruecolor($dstW, $dstH);
        if ($dst === false) {
            imagedestroy($src);

            return null;
        }

        $white = imagecolorallocate($dst, 255, 255, 255);
        if ($white !== false) {
            imagefill($dst, 0, 0, $white);
        }

        $ok = imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($src);

        if (! $ok) {
            imagedestroy($dst);

            return null;
        }

        ob_start();
        imagejpeg($dst, null, 82);
        imagedestroy($dst);
        $result = ob_get_clean();

        return is_string($result) && $result !== '' ? $result : null;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function fitSize(int $srcW, int $srcH, int $maxW, int $maxH): array
    {
        if ($srcW <= $maxW && $srcH <= $maxH) {
            return [$srcW, $srcH];
        }

        $ratio = min($maxW / $srcW, $maxH / $srcH);

        return [max(1, (int) round($srcW * $ratio)), max(1, (int) round($srcH * $ratio))];
    }
}
