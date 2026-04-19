<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_when_opening_profile(): void
    {
        $response = $this->get(route('profile.edit'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_open_profile_screen(): void
    {
        $user = $this->createUserWithRole('reporter');
        $user->forceFill([
            'name' => 'Usuario Perfil',
            'last_name' => 'Principal',
            'email' => 'perfil.principal@example.test',
            'phone' => '+51 900 111 222',
            'avatar_url' => 'https://cdn.example.test/users/avatars/owner.png',
        ])->save();

        $otherUser = $this->createUserWithRole('reporter');
        $otherUser->forceFill([
            'name' => 'Usuario Externo',
            'last_name' => 'Secundario',
            'email' => 'otro.usuario@example.test',
            'phone' => '+51 900 333 444',
            'avatar_url' => 'https://cdn.example.test/users/avatars/other.png',
        ])->save();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('Mi perfil');
        $response->assertSee($user->email);
        $response->assertSee($user->last_name);
        $response->assertSee($user->avatar_url);
        $response->assertDontSee($otherUser->email);
        $response->assertDontSee($otherUser->avatar_url);
    }

    public function test_authenticated_user_can_update_own_profile_data(): void
    {
        $user = $this->createUserWithRole('reporter');

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Nombre Actualizado',
                'last_name' => 'Apellido Actualizado',
                'email' => 'perfil.actualizado@example.test',
                'phone' => '+51 999 888 777',
            ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('status', 'Perfil actualizado correctamente.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nombre Actualizado',
            'last_name' => 'Apellido Actualizado',
            'email' => 'perfil.actualizado@example.test',
            'phone' => '+51 999 888 777',
        ]);
    }

    public function test_profile_update_only_changes_authenticated_user(): void
    {
        $user = $this->createUserWithRole('reporter');
        $otherUser = $this->createUserWithRole('reporter');
        $otherUser->forceFill([
            'name' => 'Otro Nombre',
            'last_name' => 'Otro Apellido',
            'phone' => '+51 955 111 000',
            'email' => 'other.locked@example.test',
        ])->save();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Solo Propio',
                'last_name' => 'Actualizado',
                'email' => 'solo.propio@example.test',
                'phone' => '+51 944 222 000',
            ]);

        $response->assertRedirect(route('profile.edit'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Solo Propio',
            'last_name' => 'Actualizado',
            'email' => 'solo.propio@example.test',
            'phone' => '+51 944 222 000',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $otherUser->id,
            'name' => 'Otro Nombre',
            'last_name' => 'Otro Apellido',
            'email' => 'other.locked@example.test',
            'phone' => '+51 955 111 000',
        ]);
    }

    public function test_authenticated_user_cannot_use_email_that_belongs_to_another_user(): void
    {
        $user = $this->createUserWithRole('reporter');
        $takenUser = User::factory()->create([
            'email' => 'taken@example.test',
        ]);

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => 'Intento Invalido',
                'last_name' => 'Intento Apellido',
                'email' => $takenUser->email,
                'phone' => '999999999',
            ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHasErrors('email');
    }

    public function test_authenticated_user_can_replace_own_avatar(): void
    {
        config([
            'filesystems.domain_disks.users' => 'public',
            'filesystems.domain_prefixes.users' => 'users/avatars',
        ]);
        Storage::fake('public');

        $user = $this->createUserWithRole('reporter');

        Storage::disk('public')->put('users/avatars/' . $user->id . '.png', 'legacy-avatar-content');
        $user->forceFill([
            'avatar_url' => Storage::disk('public')->url('users/avatars/' . $user->id . '.png'),
        ])->save();

        $response = $this
            ->actingAs($user)
            ->post(route('profile.update-avatar'), [
                'avatar_file' => UploadedFile::fake()->image('new-avatar.jpg', 100, 100),
            ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('status', 'Avatar actualizado correctamente.');

        Storage::disk('public')->assertMissing('users/avatars/' . $user->id . '.png');
        Storage::disk('public')->assertExists('users/avatars/' . $user->id . '.jpg');
    }

    public function test_profile_avatar_update_rejects_non_image_files(): void
    {
        config([
            'filesystems.domain_disks.users' => 'public',
            'filesystems.domain_prefixes.users' => 'users/avatars',
        ]);
        Storage::fake('public');

        $user = $this->createUserWithRole('reporter');

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('profile.update-avatar'), [
                'avatar_file' => UploadedFile::fake()->create('not-image.pdf', 200, 'application/pdf'),
            ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHasErrors('avatar_file');
    }

    private function createUserWithRole(string $role): User
    {
        $this->ensureRolesExist();

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function ensureRolesExist(): void
    {
        foreach (['reporter', 'maintenance', 'admin', 'super_admin'] as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }
    }
}
