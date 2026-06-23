<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfileAvatarTopbarSyncTest extends TestCase
{
    use RefreshDatabase;

    // ── Without avatar ────────────────────────────────────────────────────────

    #[Test]
    public function user_without_avatar_sees_initials_in_topbar(): void
    {
        $user = $this->userWithRole('reporter');
        $user->forceFill(['avatar_url' => null])->save();

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $html = $response->content();

        // topbar-avatar div should NOT contain an img tag when no avatar
        $this->assertStringNotContainsString('class="avatar-img"', $html);
    }

    #[Test]
    public function user_without_avatar_sees_initials_in_dropdown(): void
    {
        $user = $this->userWithRole('reporter');
        $user->forceFill(['name' => 'Roberto Inca', 'avatar_url' => null])->save();

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        // Initials are rendered as text; x-avatar emits class="avatar-img" only when src is present
        $response->assertSee('RI', false);
        $response->assertDontSee('class="avatar-img"', false);
    }

    // ── With avatar ───────────────────────────────────────────────────────────

    #[Test]
    public function profile_page_shows_avatar_in_main_card_when_set(): void
    {
        $user = $this->userWithRole('reporter');
        $avatarUrl = 'https://cdn.example.test/users/avatars/'.$user->id.'/avatar.jpg';
        $user->forceFill(['avatar_url' => $avatarUrl])->save();

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee($avatarUrl, false);
    }

    #[Test]
    public function profile_page_shows_avatar_in_sidebar_when_set(): void
    {
        $user = $this->userWithRole('reporter');
        $avatarUrl = 'https://cdn.example.test/users/avatars/'.$user->id.'/avatar.jpg';
        $user->forceFill(['avatar_url' => $avatarUrl])->save();

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        // URL appears in the sidebar img src (profile-identity-img)
        $response->assertSee('profile-identity-img', false);
    }

    #[Test]
    public function topbar_shows_img_tag_when_user_has_avatar(): void
    {
        $user = $this->userWithRole('reporter');
        $avatarUrl = 'https://cdn.example.test/users/avatars/'.$user->id.'/photo.png';
        $user->forceFill(['avatar_url' => $avatarUrl])->save();

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $html = $response->content();

        // avatar-img class is rendered (from x-avatar with src)
        $this->assertStringContainsString('class="avatar-img"', $html);
        // Avatar URL with cache-busting appears in the topbar area
        $this->assertStringContainsString($avatarUrl.'?v=', $html);
    }

    #[Test]
    public function dropdown_shows_img_tag_when_user_has_avatar(): void
    {
        $user = $this->userWithRole('reporter');
        $avatarUrl = 'https://cdn.example.test/users/avatars/'.$user->id.'/photo.png';
        $user->forceFill(['avatar_url' => $avatarUrl])->save();

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $html = $response->content();

        // avatar-img appears at least twice: topbar trigger + dropdown head
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'class="avatar-img"'));
    }

    #[Test]
    public function avatar_url_with_cache_busting_appears_in_page_after_update(): void
    {
        config([
            'services.supabase.storage.domain_buckets.users' => 'TablaUsers',
            'services.supabase.storage.use_local_disk_for_testing' => true,
            'services.supabase.storage.testing_disk' => 'public',
            'filesystems.domain_prefixes.users' => 'users/avatars',
        ]);
        Storage::fake('public');

        $user = $this->userWithRole('reporter');

        $this->actingAs($user)->post(route('profile.update-avatar'), [
            'avatar_file' => UploadedFile::fake()->image('selfie.jpg', 120, 120),
        ])->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertNotNull($user->avatar_url);

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $html = $response->content();
        $this->assertStringContainsString('class="avatar-img"', $html);
    }

    #[Test]
    public function topbar_shows_img_when_navigating_to_another_page_with_avatar(): void
    {
        $user = $this->userWithRole('reporter');
        $avatarUrl = 'https://cdn.example.test/users/avatars/'.$user->id.'/photo.png';
        $user->forceFill(['avatar_url' => $avatarUrl])->save();

        // reporter.dashboard is the reporter-specific page; topbar is part of the shared layout
        $response = $this->actingAs($user)->get(route('reporter.dashboard'));

        $response->assertOk();
        $html = $response->content();
        $this->assertStringContainsString('class="avatar-img"', $html);
        $this->assertStringContainsString($avatarUrl.'?v=', $html);
    }

    #[Test]
    public function after_deleting_avatar_topbar_falls_back_to_initials(): void
    {
        config([
            'services.supabase.storage.domain_buckets.users' => 'TablaUsers',
            'services.supabase.storage.use_local_disk_for_testing' => true,
            'services.supabase.storage.testing_disk' => 'public',
            'filesystems.domain_prefixes.users' => 'users/avatars',
        ]);
        Storage::fake('public');

        $user = $this->userWithRole('reporter');
        $path = 'users/avatars/'.$user->id.'/avatar.png';
        Storage::disk('public')->put($path, 'fake-image');
        $user->forceFill(['avatar_url' => '/storage/v1/object/public/TablaUsers/'.$path])->save();

        $this->actingAs($user)->delete(route('profile.delete-avatar'))
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertNull($user->avatar_url);

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $this->assertStringNotContainsString('class="avatar-img"', $response->content());
    }

    #[Test]
    public function avatar_display_url_returns_null_when_no_avatar(): void
    {
        $user = $this->userWithRole('reporter');
        $user->forceFill(['avatar_url' => null])->save();

        $this->assertNull($user->avatarDisplayUrl());
    }

    #[Test]
    public function avatar_display_url_returns_url_with_version_param(): void
    {
        $user = $this->userWithRole('reporter');
        $user->forceFill(['avatar_url' => 'https://cdn.example.test/avatar.jpg'])->save();

        $displayUrl = $user->avatarDisplayUrl();

        $this->assertNotNull($displayUrl);
        $this->assertStringStartsWith('https://cdn.example.test/avatar.jpg?v=', (string) $displayUrl);
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
