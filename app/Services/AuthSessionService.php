<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class AuthSessionService
{
    public const REFRESH_ABILITY = 'session:refresh';

    /**
     * Issue an access/refresh pair. Refresh tokens have no API abilities and
     * can only be exchanged at the dedicated refresh endpoint.
     *
     * @return array{token:string,refresh_token:string,access_token_expires_at:string,refresh_token_expires_at:string,session_id:string}
     */
    public function issue(User $user, string $audience = 'app', ?string $sessionId = null): array
    {
        $sessionId ??= (string) Str::uuid();
        $accessExpiresAt = now()->addMinutes(max(5, (int) config('sanctum.access_token_expiration', 60)));
        $refreshExpiresAt = now()->addMinutes(max(60, (int) config('sanctum.refresh_token_expiration', 43200)));

        $access = $user->createToken(
            $this->accessName($sessionId, $audience),
            ['*'],
            $accessExpiresAt,
        );
        $refresh = $user->createToken(
            $this->refreshName($sessionId, $audience),
            [self::REFRESH_ABILITY],
            $refreshExpiresAt,
        );

        return [
            'token' => $access->plainTextToken,
            'refresh_token' => $refresh->plainTextToken,
            'access_token_expires_at' => $accessExpiresAt->toIso8601String(),
            'refresh_token_expires_at' => $refreshExpiresAt->toIso8601String(),
            'session_id' => $sessionId,
        ];
    }

    /**
     * Rotate a refresh token exactly once. Row locking ensures two concurrent
     * refresh requests cannot both mint a valid replacement session.
     *
     * @return array{user:User,session:array{token:string,refresh_token:string,access_token_expires_at:string,refresh_token_expires_at:string,session_id:string}}|null
     */
    public function rotate(string $plainRefreshToken): ?array
    {
        if (! str_contains($plainRefreshToken, '|')) {
            return null;
        }

        [$id, $secret] = explode('|', $plainRefreshToken, 2);
        if (! ctype_digit($id) || $secret === '') {
            return null;
        }

        return DB::transaction(function () use ($id, $secret): ?array {
            /** @var PersonalAccessToken|null $refresh */
            $refresh = PersonalAccessToken::query()->lockForUpdate()->find((int) $id);
            if (! $refresh || ! hash_equals((string) $refresh->token, hash('sha256', $secret))) {
                return null;
            }

            if (! $refresh->can(self::REFRESH_ABILITY) || $refresh->expires_at?->isPast()) {
                $refresh->delete();

                return null;
            }

            $parts = $this->parseName((string) $refresh->name);
            if (! $parts || $parts['kind'] !== 'refresh') {
                return null;
            }

            /** @var User|null $user */
            $user = $refresh->tokenable;
            if (! $user instanceof User) {
                $refresh->delete();

                return null;
            }

            $this->deleteSessionTokens($user, $parts['session_id']);

            return [
                'user' => $user,
                'session' => $this->issue($user, $parts['audience'], $parts['session_id']),
            ];
        }, 3);
    }

    public function revokeCurrentSession(User $user): void
    {
        $current = $user->currentAccessToken();
        if (! $current) {
            return;
        }

        $parts = $this->parseName((string) $current->name);
        if ($parts) {
            $this->deleteSessionTokens($user, $parts['session_id']);

            return;
        }

        $current->delete();
    }

    private function deleteSessionTokens(User $user, string $sessionId): void
    {
        $user->tokens()
            ->where(function ($query) use ($sessionId) {
                $query->where('name', 'like', 'access:'.$sessionId.':%')
                    ->orWhere('name', 'like', 'refresh:'.$sessionId.':%');
            })
            ->delete();
    }

    private function accessName(string $sessionId, string $audience): string
    {
        return 'access:'.$sessionId.':'.$this->cleanAudience($audience);
    }

    private function refreshName(string $sessionId, string $audience): string
    {
        return 'refresh:'.$sessionId.':'.$this->cleanAudience($audience);
    }

    private function cleanAudience(string $audience): string
    {
        return Str::limit(preg_replace('/[^a-z0-9_-]/i', '', $audience) ?: 'app', 32, '');
    }

    /** @return array{kind:string,session_id:string,audience:string}|null */
    private function parseName(string $name): ?array
    {
        $parts = explode(':', $name, 3);
        if (count($parts) !== 3 || ! in_array($parts[0], ['access', 'refresh'], true)) {
            return null;
        }

        return [
            'kind' => $parts[0],
            'session_id' => $parts[1],
            'audience' => $parts[2],
        ];
    }
}
