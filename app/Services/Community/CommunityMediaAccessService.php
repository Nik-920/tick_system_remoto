<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Models\Ticket;
use App\Models\TicketMedia;
use Illuminate\Support\Facades\Storage;

final class CommunityMediaAccessService
{
    /** @var list<string> */
    private const ALLOWED_STATES = [
        Ticket::STATE_OPEN,
        Ticket::STATE_IN_PROGRESS,
        Ticket::STATE_RESOLVED,
    ];

    /**
     * Returns true only when the media belongs to a ticket that is currently
     * visible in the Community feed (community_visible=true, non-terminal state).
     *
     * Admin hide sets community_visible=false, so no separate hidden_at check needed.
     */
    public function canViewInCommunity(TicketMedia $media): bool
    {
        $ticket = $media->ticket;

        if ($ticket === null) {
            return false;
        }

        if (! $ticket->community_visible) {
            return false;
        }

        return in_array((string) $ticket->state, self::ALLOWED_STATES, true);
    }

    /**
     * Only image-category files can be previewed inline.
     * file_type may be the full MIME (image/jpeg) or just the category (image).
     */
    public function isPreviewable(TicketMedia $media): bool
    {
        return str_starts_with((string) ($media->file_type ?? ''), 'image');
    }

    /**
     * Resolves the relative path within the public Storage disk.
     *
     * Handles two URL shapes persisted in file_url:
     *   - Supabase:  https://xxx/storage/v1/object/public/{bucket}/{path}
     *   - Local:     http://localhost/storage/{path}  (public disk URL)
     *
     * Returns null when the path cannot be extracted or the file does not exist
     * on the public disk (e.g. production Supabase files in a local dev context).
     */
    public function resolvePath(TicketMedia $media): ?string
    {
        $fileUrl = trim((string) ($media->file_url ?? ''));
        if ($fileUrl === '') {
            return null;
        }

        $path = $this->extractStoragePath($fileUrl);
        if ($path === null || $path === '') {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return $path;
    }

    /**
     * Returns a safe Content-Type for the response.
     * Defaults to image/jpeg when only the category (image) is stored.
     */
    public function contentType(TicketMedia $media): string
    {
        $fileType = (string) ($media->file_type ?? '');

        if (str_contains($fileType, '/')) {
            return $fileType;
        }

        return 'image/jpeg';
    }

    private function extractStoragePath(string $fileUrl): ?string
    {
        $parsedPath = parse_url($fileUrl, PHP_URL_PATH);
        if (! is_string($parsedPath)) {
            return null;
        }

        $normalized = ltrim(trim($parsedPath), '/');

        // Supabase: storage/v1/object/public/{bucket}/{encoded_path}
        if (str_starts_with($normalized, 'storage/v1/object/public/')) {
            $afterPublic = substr($normalized, strlen('storage/v1/object/public/'));
            $slashPos = strpos($afterPublic, '/');
            if ($slashPos === false) {
                return null;
            }

            $encodedObjectPath = substr($afterPublic, $slashPos + 1);
            if ($encodedObjectPath === '') {
                return null;
            }

            $segments = array_values(array_filter(
                explode('/', $encodedObjectPath),
                static fn (string $p): bool => $p !== ''
            ));

            return implode('/', array_map('rawurldecode', $segments));
        }

        // Local public disk URL: storage/{relative_path}
        if (str_starts_with($normalized, 'storage/')) {
            return substr($normalized, strlen('storage/'));
        }

        return null;
    }
}
