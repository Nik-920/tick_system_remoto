<?php

namespace App\Services\Storage;

use App\Models\User;
use Illuminate\Http\UploadedFile;

class UserAvatarStorageService
{
    public function __construct(private DomainStorageService $domainStorage)
    {
    }

    public function replaceAvatar(User $user, UploadedFile $file): string
    {
        $extension = $this->safeExtension($file->getClientOriginalExtension());

        return $this->domainStorage->replaceUploadedFile(
            'users',
            $user->avatar_url,
            $file,
            $this->domainStorage->pathPrefix('users'),
            $user->id . '.' . $extension
        );
    }

    private function safeExtension(?string $extension): string
    {
        $normalized = strtolower(trim((string) $extension));

        if ($normalized === '') {
            return 'png';
        }

        return preg_replace('/[^a-z0-9]+/', '', $normalized) ?: 'png';
    }
}
