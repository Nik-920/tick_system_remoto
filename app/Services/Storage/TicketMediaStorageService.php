<?php

namespace App\Services\Storage;

use App\Models\Ticket;
use App\Models\TicketMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class TicketMediaStorageService
{
    public function __construct(private DomainStorageService $domainStorage)
    {
    }

    /**
     * @param array<int, UploadedFile> $files
     */
    public function storeManyForTicket(Ticket $ticket, User $uploadedBy, array $files): void
    {
        $nameCounts = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $this->storeForTicketInternal($ticket, $uploadedBy, $file, $nameCounts);
        }
    }

    public function storeForTicket(Ticket $ticket, User $uploadedBy, UploadedFile $file): TicketMedia
    {
        $nameCounts = [];

        return $this->storeForTicketInternal($ticket, $uploadedBy, $file, $nameCounts);
    }

    public function deleteByUrl(?string $fileUrl): void
    {
        $this->domainStorage->deleteManagedUrl('tickets', $fileUrl);
    }

    /**
     * @param iterable<int, mixed> $fileUrls
     */
    public function deleteManyByUrls(iterable $fileUrls): void
    {
        $processedUrls = [];

        foreach ($fileUrls as $fileUrl) {
            if (! is_string($fileUrl)) {
                continue;
            }

            $normalizedUrl = trim($fileUrl);
            if ($normalizedUrl === '' || isset($processedUrls[$normalizedUrl])) {
                continue;
            }

            $processedUrls[$normalizedUrl] = true;
            $this->deleteByUrl($normalizedUrl);
        }
    }

    /**
     * @param array<string, int> $nameCounts
     */
    private function storeForTicketInternal(Ticket $ticket, User $uploadedBy, UploadedFile $file, array &$nameCounts): TicketMedia
    {
        $fileName = $this->resolveFileName($file, $nameCounts);

        $fileUrl = $this->domainStorage->storeUploadedFile(
            'tickets',
            $file,
            $this->pathPrefixForTicket($ticket),
            $fileName
        );

        return TicketMedia::query()->create([
            'ticket_id' => $ticket->id,
            'file_url' => $fileUrl,
            'file_type' => $this->resolveFileType($file),
            'uploaded_by' => $uploadedBy->id,
        ]);
    }

    /**
     * @param array<string, int> $nameCounts
     */
    private function resolveFileName(UploadedFile $file, array &$nameCounts): string
    {
        $baseFileName = SanitizedFileName::fromUploadedFile($file, 'ticket-media', 'bin');
        $key = strtolower($baseFileName);
        $seenCount = $nameCounts[$key] ?? 0;
        $nameCounts[$key] = $seenCount + 1;

        if ($seenCount === 0) {
            return $baseFileName;
        }

        $namePart = (string) pathinfo($baseFileName, PATHINFO_FILENAME);
        $extensionPart = (string) pathinfo($baseFileName, PATHINFO_EXTENSION);
        $suffixedName = $namePart . '-' . $seenCount;

        return $extensionPart === ''
            ? $suffixedName
            : $suffixedName . '.' . $extensionPart;
    }

    private function pathPrefixForTicket(Ticket $ticket): string
    {
        $basePrefix = $this->domainStorage->pathPrefix('tickets');

        return $basePrefix === ''
            ? (string) $ticket->id
            : $basePrefix . '/' . $ticket->id;
    }

    private function resolveFileType(UploadedFile $file): string
    {
        $mimeType = strtolower((string) $file->getMimeType());

        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }

        if (
            $mimeType === 'application/pdf'
            || str_contains($mimeType, 'word')
            || str_contains($mimeType, 'document')
            || str_contains($mimeType, 'excel')
            || str_contains($mimeType, 'sheet')
            || str_contains($mimeType, 'presentation')
            || str_starts_with($mimeType, 'text/')
        ) {
            return 'document';
        }

        return 'other';
    }
}
