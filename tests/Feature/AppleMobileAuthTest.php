<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppleMobileAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['api.enabled' => false]);
    }

    public function test_links_apple_login_to_existing_email_account(): void
    {
        $role = Role::create([
            'name' => 'customer',
            'display_name' => 'Customer',
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
            'email' => 'same-person@gmail.com',
        ]);

        $response = $this->postJson('/api/v1/auth/apple/mobile', [
            'apple_user_id' => 'apple-user-123',
            'email' => 'same-person@gmail.com',
            'name' => 'Same Person',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.user.id', $user->id);
        $response->assertJsonPath('data.auth_provider', 'apple');
        $response->assertJsonPath('data.is_new_user', false);

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'apple',
            'provider_user_id' => 'apple-user-123',
            'email' => 'same-person@gmail.com',
        ]);
    }

    public function test_creates_placeholder_email_when_apple_omits_email(): void
    {
        Role::create([
            'name' => 'customer',
            'display_name' => 'Customer',
        ]);

        $response = $this->postJson('/api/v1/auth/apple/mobile', [
            'apple_user_id' => 'apple-user-no-email',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.auth_provider', 'apple');
        $response->assertJsonPath('data.is_new_user', true);

        $user = User::query()->first();
        $this->assertNotNull($user);
        $this->assertStringEndsWith('@apple-auth.local', $user->email);

        $this->assertTrue(SocialAccount::where('provider', 'apple')
            ->where('provider_user_id', 'apple-user-no-email')
            ->where('user_id', $user->id)
            ->exists());
    }
}
