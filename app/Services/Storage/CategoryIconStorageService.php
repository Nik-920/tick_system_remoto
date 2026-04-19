<?php

namespace App\Services\Storage;

use App\Models\Category;
use Illuminate\Http\UploadedFile;

class CategoryIconStorageService
{
    private const DOMAIN = 'categories';

    public function __construct(
        private readonly DomainStorageService $domainStorage,
    ) {
    }

    public function replaceIcon(Category $category, UploadedFile $file, ?string $previousIcon): string
    {
        $extension = $this->safeExtension($file);
        $fileName = $category->id . '.' . $extension;

        return $this->domainStorage->replaceUploadedFile(
            self::DOMAIN,
            $previousIcon,
            $file,
            $this->domainStorage->pathPrefix(self::DOMAIN),
            $fileName,
        );
    }

    private function safeExtension(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        if (! is_string($extension) || $extension === '') {
            $extension = $file->extension();
        }

        if (! is_string($extension) || $extension === '') {
            return 'png';
        }

        $sanitized = preg_replace('/[^a-z0-9]/', '', strtolower($extension));

        return is_string($sanitized) && $sanitized !== ''
            ? $sanitized
            : 'png';
    }
}
