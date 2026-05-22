<?php

namespace Tests\Unit\Services\Storage;

use App\Services\Storage\SupabaseStorageClient;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class SupabaseStorageClientTest extends TestCase
{
    public function test_upload_contents_stores_file_on_local_disk(): void
    {
        config([
            'services.supabase.storage.use_local_disk_for_testing' => true,
            'services.supabase.storage.testing_disk' => 'public',
        ]);
        Storage::fake('public');

        $client = new SupabaseStorageClient;
        $client->uploadContents('bucket', '/tickets/sample.txt', 'hello', 'text/plain');

        Storage::disk('public')->assertExists('tickets/sample.txt');
    }

    public function test_delete_object_removes_file_on_local_disk(): void
    {
        config([
            'services.supabase.storage.use_local_disk_for_testing' => true,
            'services.supabase.storage.testing_disk' => 'public',
        ]);
        Storage::fake('public');
        Storage::disk('public')->put('tickets/remove.txt', 'bye');

        $client = new SupabaseStorageClient;
        $client->deleteObject('bucket', 'tickets/remove.txt');

        Storage::disk('public')->assertMissing('tickets/remove.txt');
    }

    public function test_copy_object_returns_false_when_source_missing(): void
    {
        config([
            'services.supabase.storage.use_local_disk_for_testing' => true,
            'services.supabase.storage.testing_disk' => 'public',
        ]);
        Storage::fake('public');

        $client = new SupabaseStorageClient;

        $this->assertFalse($client->copyObject('bucket', 'missing.txt', 'target.txt'));
    }

    public function test_public_url_encodes_segments(): void
    {
        config([
            'services.supabase.storage.public_base_url' => 'https://supabase.test',
        ]);

        $client = new SupabaseStorageClient;
        $url = $client->publicUrl('My Bucket', 'folder/space name.png');

        $this->assertSame(
            'https://supabase.test/storage/v1/object/public/My%20Bucket/folder/space%20name.png',
            $url
        );
    }

    public function test_upload_contents_throws_when_path_is_empty(): void
    {
        config([
            'services.supabase.storage.use_local_disk_for_testing' => true,
            'services.supabase.storage.testing_disk' => 'public',
        ]);
        Storage::fake('public');

        $client = new SupabaseStorageClient;

        $this->expectException(RuntimeException::class);

        $client->uploadContents('bucket', ' ', 'data');
    }
}
