<?php

namespace Tests\Feature\Web;

use App\Models\FcmToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FcmTokenControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_store_fcm_token_and_update_device(): void
    {
        $user = $this->createUserWithRole('reporter');

        $response = $this->actingAs($user)->postJson(route('fcm.store'), [
            'token' => 'token-abc',
            'device' => 'web',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('fcm_tokens', [
            'user_id' => $user->id,
            'token' => 'token-abc',
            'device' => 'web',
        ]);

        $response = $this->actingAs($user)->postJson(route('fcm.store'), [
            'token' => 'token-abc',
            'device' => 'mobile',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseCount('fcm_tokens', 1);
        $this->assertDatabaseHas('fcm_tokens', [
            'user_id' => $user->id,
            'token' => 'token-abc',
            'device' => 'mobile',
        ]);
    }

    public function test_store_requires_token(): void
    {
        $user = $this->createUserWithRole('reporter');

        $response = $this->actingAs($user)->postJson(route('fcm.store'), [
            'device' => 'web',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['token']);
    }

    public function test_user_can_delete_own_token_only(): void
    {
        $user = $this->createUserWithRole('reporter');
        $otherUser = $this->createUserWithRole('reporter');

        FcmToken::create([
            'user_id' => $user->id,
            'token' => 'token-own',
            'device' => 'web',
        ]);

        FcmToken::create([
            'user_id' => $otherUser->id,
            'token' => 'token-foreign',
            'device' => 'web',
        ]);

        $response = $this->actingAs($user)->deleteJson(route('fcm.destroy'), [
            'token' => 'token-own',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseMissing('fcm_tokens', [
            'user_id' => $user->id,
            'token' => 'token-own',
        ]);

        $this->assertDatabaseHas('fcm_tokens', [
            'user_id' => $otherUser->id,
            'token' => 'token-foreign',
        ]);
    }

    public function test_destroy_requires_token(): void
    {
        $user = $this->createUserWithRole('reporter');

        $response = $this->actingAs($user)->deleteJson(route('fcm.destroy'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['token']);
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
