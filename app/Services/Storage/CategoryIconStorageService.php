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
        $basePrefix = $this->domainStorage->pathPrefix(self::DOMAIN);
        $pathPrefix = $basePrefix === ''
            ? (string) $category->id
            : $basePrefix.'/'.$category->id;
        $fileName = SanitizedFileName::fromUploadedFile($file, 'category-icon', 'png');

        return $this->domainStorage->replaceUploadedFile(
            self::DOMAIN,
            $previousIcon,
            $file,
            $pathPrefix,
            $fileName,
        );
    }

    public function deleteIcon(?string $iconUrl): void
    {
        $this->domainStorage->deleteManagedUrl(self::DOMAIN, $iconUrl);
    }
}
