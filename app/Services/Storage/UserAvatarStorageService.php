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
        $basePrefix = $this->domainStorage->pathPrefix('users');
        $pathPrefix = $basePrefix === ''
            ? (string) $user->id
            : $basePrefix.'/'.$user->id;

        $fileName = SanitizedFileName::fromUploadedFile($file, 'avatar', 'png');

        return $this->domainStorage->replaceUploadedFile(
            'users',
            $user->avatar_url,
            $file,
            $pathPrefix,
            $fileName
        );
    }
}
