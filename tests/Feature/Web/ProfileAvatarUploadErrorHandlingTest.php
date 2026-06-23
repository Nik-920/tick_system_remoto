<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Http\Requests\UpdateUserAvatarRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfileAvatarUploadErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    // ── Request messages ──────────────────────────────────────────────────────

    #[Test]
    public function avatar_request_has_human_message_for_uploaded_rule(): void
    {
        $request = new UpdateUserAvatarRequest;
        $messages = $request->messages();

        $this->assertArrayHasKey('avatar_file.uploaded', $messages);
        $this->assertStringNotContainsString('validation.', $messages['avatar_file.uploaded']);
    }

    #[Test]
    public function avatar_request_has_human_message_for_mimes_rule(): void
    {
        $request = new UpdateUserAvatarRequest;
        $messages = $request->messages();

        $this->assertArrayHasKey('avatar_file.mimes', $messages);
        $this->assertStringNotContainsString('validation.', $messages['avatar_file.mimes']);
    }

    #[Test]
    public function avatar_request_has_human_message_for_max_rule(): void
    {
        $request = new UpdateUserAvatarRequest;
        $messages = $request->messages();

        $this->assertArrayHasKey('avatar_file.max', $messages);
        $this->assertStringNotContainsString('validation.', $messages['avatar_file.max']);
    }

    #[Test]
    public function avatar_request_includes_mimes_rule(): void
    {
        $request = new UpdateUserAvatarRequest;
        $rules = $request->rules();

        $this->assertContains('mimes:jpg,jpeg,png,webp', $rules['avatar_file']);
    }

    // ── Backend validation ────────────────────────────────────────────────────

    #[Test]
    public function avatar_upload_rejects_file_over_2mb_with_human_message(): void
    {
        $user = $this->userWithRole('reporter');
        $oversized = UploadedFile::fake()->create('big-photo.jpg', 3 * 1024, 'image/jpeg');

        $response = $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.update-avatar'), ['avatar_file' => $oversized]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHasErrors('avatar_file');

        $errorMessage = session('errors')?->first('avatar_file') ?? '';
        $this->assertStringNotContainsString('validation.', $errorMessage);
        $this->assertNotEmpty($errorMessage);
    }

    #[Test]
    public function avatar_upload_rejects_pdf_with_human_message(): void
    {
        $user = $this->userWithRole('reporter');
        $pdf = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.update-avatar'), ['avatar_file' => $pdf]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHasErrors('avatar_file');

        $errorMessage = session('errors')?->first('avatar_file') ?? '';
        $this->assertStringNotContainsString('validation.', $errorMessage);
    }

    #[Test]
    public function avatar_upload_rejects_svg_file(): void
    {
        $user = $this->userWithRole('reporter');
        // SVG is not in the allowed mimes list
        $svg = UploadedFile::fake()->create('icon.svg', 10, 'image/svg+xml');

        $response = $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.update-avatar'), ['avatar_file' => $svg]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHasErrors('avatar_file');
    }

    #[Test]
    public function avatar_upload_rejects_missing_file_with_human_message(): void
    {
        $user = $this->userWithRole('reporter');

        $response = $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.update-avatar'), []);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHasErrors('avatar_file');

        $errorMessage = session('errors')?->first('avatar_file') ?? '';
        $this->assertStringNotContainsString('validation.', $errorMessage);
    }

    #[Test]
    public function avatar_error_response_never_contains_validation_uploaded_string(): void
    {
        $user = $this->userWithRole('reporter');
        $pdf = UploadedFile::fake()->create('file.pdf', 200, 'application/pdf');

        $response = $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.update-avatar'), ['avatar_file' => $pdf]);

        $response->assertRedirect(route('profile.edit'));
        $errors = session('errors')?->all() ?? [];

        foreach ($errors as $error) {
            $this->assertStringNotContainsString('validation.uploaded', $error);
        }
    }

    #[Test]
    public function valid_jpg_avatar_is_accepted(): void
    {
        config([
            'services.supabase.storage.domain_buckets.users' => 'TablaUsers',
            'services.supabase.storage.use_local_disk_for_testing' => true,
            'services.supabase.storage.testing_disk' => 'public',
            'filesystems.domain_prefixes.users' => 'users/avatars',
        ]);
        Storage::fake('public');

        $user = $this->userWithRole('reporter');

        $response = $this->actingAs($user)
            ->post(route('profile.update-avatar'), [
                'avatar_file' => UploadedFile::fake()->image('face.jpg', 100, 100),
            ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', 'Avatar actualizado correctamente.');
    }

    #[Test]
    public function valid_png_avatar_is_accepted(): void
    {
        config([
            'services.supabase.storage.domain_buckets.users' => 'TablaUsers',
            'services.supabase.storage.use_local_disk_for_testing' => true,
            'services.supabase.storage.testing_disk' => 'public',
            'filesystems.domain_prefixes.users' => 'users/avatars',
        ]);
        Storage::fake('public');

        $user = $this->userWithRole('reporter');

        $response = $this->actingAs($user)
            ->post(route('profile.update-avatar'), [
                'avatar_file' => UploadedFile::fake()->image('face.png', 100, 100),
            ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHasNoErrors();
    }

    // ── Frontend upload guard presence ────────────────────────────────────────

    #[Test]
    public function profile_avatar_input_has_upload_guard_attribute(): void
    {
        $user = $this->userWithRole('reporter');

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('data-upload-guard', false);
    }

    #[Test]
    public function profile_avatar_input_has_2mb_max_size_attribute(): void
    {
        $user = $this->userWithRole('reporter');

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('data-max-file-size="2097152"', false);
        $response->assertSee('data-max-file-size-label="2 MB"', false);
    }

    #[Test]
    public function profile_avatar_input_lists_allowed_extensions(): void
    {
        $user = $this->userWithRole('reporter');

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('data-allowed-extensions="jpg,jpeg,png,webp"', false);
    }

    #[Test]
    public function upload_error_modal_is_present_on_profile_page(): void
    {
        $user = $this->userWithRole('reporter');

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $html = $response->content();
        $this->assertSame(1, substr_count($html, 'data-upload-error-modal'));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
