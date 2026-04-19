<?php

namespace App\Services\Storage;

use App\Models\Ticket;
use App\Models\TicketMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

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
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $this->storeForTicket($ticket, $uploadedBy, $file);
        }
    }

    public function storeForTicket(Ticket $ticket, User $uploadedBy, UploadedFile $file): TicketMedia
    {
        $extension = $this->safeExtension($file->getClientOriginalExtension());
        $fileName = (string) Str::uuid() . '.' . $extension;

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

    private function pathPrefixForTicket(Ticket $ticket): string
    {
        $basePrefix = $this->domainStorage->pathPrefix('tickets');

        return $basePrefix === ''
            ? $ticket->id
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

    private function safeExtension(?string $extension): string
    {
        $normalized = strtolower(trim((string) $extension));

        if ($normalized === '') {
            return 'bin';
        }

        return preg_replace('/[^a-z0-9]+/', '', $normalized) ?: 'bin';
    }
}
