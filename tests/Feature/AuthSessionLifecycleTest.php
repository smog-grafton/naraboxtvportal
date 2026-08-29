<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuthSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthSessionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_tokens_rotate_once_and_logout_revokes_the_pair(): void
    {
        $user = User::factory()->create();
        $session = app(AuthSessionService::class)->issue($user, 'app');
        $headers = ['X-API-KEY' => (string) config('api.key')];

        $this->assertSame(2, $user->tokens()->count());

        $rotated = $this->withHeaders($headers)
            ->postJson('/api/v1/auth/refresh', ['refresh_token' => $session['refresh_token']])
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'token',
                'refresh_token',
                'access_token_expires_at',
                'refresh_token_expires_at',
                'user' => ['id', 'email'],
            ]]);

        $this->withHeaders($headers)
            ->postJson('/api/v1/auth/refresh', ['refresh_token' => $session['refresh_token']])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'INVALID_REFRESH_TOKEN');

        $accessToken = $rotated->json('data.token');
        $this->withHeaders([...$headers, 'Authorization' => 'Bearer '.$accessToken])
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->withHeaders([...$headers, 'Authorization' => 'Bearer '.$accessToken])
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_authentication_middleware_returns_an_explicit_reauth_code(): void
    {
        $this->withHeader('X-API-KEY', (string) config('api.key'))
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'SESSION_REAUTH_REQUIRED');
    }

    public function test_password_change_requires_the_current_password_and_revokes_every_device(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Old-password-1')]);
        $currentDevice = app(AuthSessionService::class)->issue($user, 'app');
        app(AuthSessionService::class)->issue($user, 'web');
        $headers = [
            'X-API-KEY' => (string) config('api.key'),
            'Authorization' => 'Bearer '.$currentDevice['token'],
        ];

        $this->assertSame(4, $user->tokens()->count());

        $this->withHeaders($headers)->postJson('/api/v1/auth/change-password', [
            'current_password' => 'wrong-password',
            'password' => 'New-password-2',
            'password_confirmation' => 'New-password-2',
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'CURRENT_PASSWORD_INVALID');

        $this->assertSame(4, $user->tokens()->count());

        $this->withHeaders($headers)->postJson('/api/v1/auth/change-password', [
            'current_password' => 'Old-password-1',
            'password' => 'New-password-2',
            'password_confirmation' => 'New-password-2',
        ])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->assertTrue(Hash::check('New-password-2', $user->fresh()->password));
    }
}
