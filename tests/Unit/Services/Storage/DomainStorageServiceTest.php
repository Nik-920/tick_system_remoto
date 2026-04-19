<?php

namespace Tests\Unit\Services\Storage;

use App\Services\Storage\DomainStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DomainStorageServiceTest extends TestCase
{
    public function test_store_uploaded_file_uses_domain_disk_and_prefix(): void
    {
        config(['filesystems.domain_disks.categories' => 'public']);
        Storage::fake('public');

        $service = new DomainStorageService();

        $url = $service->storeUploadedFile(
            'categories',
            UploadedFile::fake()->image('icon.png', 64, 64),
            'categories/icons',
            'category-a.png'
        );

        $this->assertStringContainsString('/storage/categories/icons/category-a.png', $url);
        Storage::disk('public')->assertExists('categories/icons/category-a.png');
    }

    public function test_replace_uploaded_file_deletes_previous_when_paths_differ(): void
    {
        config(['filesystems.domain_disks.categories' => 'public']);
        Storage::fake('public');

        $service = new DomainStorageService();

        Storage::disk('public')->put('categories/icons/old.png', 'old');
        $previousUrl = Storage::disk('public')->url('categories/icons/old.png');

        $newUrl = $service->replaceUploadedFile(
            'categories',
            $previousUrl,
            UploadedFile::fake()->image('new.png', 64, 64),
            'categories/icons',
            'new.png'
        );

        $this->assertStringContainsString('/storage/categories/icons/new.png', $newUrl);
        Storage::disk('public')->assertMissing('categories/icons/old.png');
        Storage::disk('public')->assertExists('categories/icons/new.png');
    }

    public function test_replace_uploaded_file_keeps_file_when_previous_points_to_same_path(): void
    {
        config(['filesystems.domain_disks.categories' => 'public']);
        Storage::fake('public');

        $service = new DomainStorageService();

        Storage::disk('public')->put('categories/icons/same.png', 'old');
        $previousUrl = Storage::disk('public')->url('categories/icons/same.png');

        $newUrl = $service->replaceUploadedFile(
            'categories',
            $previousUrl,
            UploadedFile::fake()->image('same.png', 64, 64),
            'categories/icons',
            'same.png'
        );

        $this->assertStringContainsString('/storage/categories/icons/same.png', $newUrl);
        Storage::disk('public')->assertExists('categories/icons/same.png');
    }

    public function test_delete_managed_url_ignores_external_urls(): void
    {
        config(['filesystems.domain_disks.categories' => 'public']);
        Storage::fake('public');

        $service = new DomainStorageService();

        Storage::disk('public')->put('categories/icons/keep.png', 'keep');

        $service->deleteManagedUrl('categories', 'https://cdn.example.com/icons/keep.png');

        Storage::disk('public')->assertExists('categories/icons/keep.png');
    }

    public function test_store_contents_uses_domain_disk_and_prefix(): void
    {
        config(['filesystems.domain_disks.locations' => 'public']);
        Storage::fake('public');

        $service = new DomainStorageService();

        $url = $service->storeContents(
            'locations',
            'locations/qr-codes',
            'room-a.png',
            'png-binary-content'
        );

        $this->assertStringContainsString('/storage/locations/qr-codes/room-a.png', $url);
        Storage::disk('public')->assertExists('locations/qr-codes/room-a.png');
    }

    public function test_replace_contents_deletes_previous_when_paths_differ(): void
    {
        config(['filesystems.domain_disks.locations' => 'public']);
        Storage::fake('public');

        $service = new DomainStorageService();

        Storage::disk('public')->put('locations/qr-codes/old.png', 'old-content');
        $previousUrl = Storage::disk('public')->url('locations/qr-codes/old.png');

        $newUrl = $service->replaceContents(
            'locations',
            $previousUrl,
            'locations/qr-codes',
            'new.png',
            'new-content'
        );

        $this->assertStringContainsString('/storage/locations/qr-codes/new.png', $newUrl);
        Storage::disk('public')->assertMissing('locations/qr-codes/old.png');
        Storage::disk('public')->assertExists('locations/qr-codes/new.png');
    }
}
