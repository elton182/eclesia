<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class AuthTokenService
{
    public const CHANNEL_WEB = 'web';

    public const SESSION_ABILITY_PREFIX = 'session:';

    public const WEB_TOKEN_NAMES = [
        WebAuthService::ACCESS_TOKEN_NAME,
        WebAuthService::REFRESH_TOKEN_NAME,
    ];

    public function newSessionId(): string
    {
        return (string) Str::ulid();
    }

    /**
     * @return list<string>
     */
    public function abilitiesForSession(string $sessionId): array
    {
        return ['*', self::SESSION_ABILITY_PREFIX.$sessionId];
    }

    public function sessionIdFromToken(?PersonalAccessToken $token): ?string
    {
        if ($token === null) {
            return null;
        }

        foreach ($token->abilities ?? [] as $ability) {
            if (is_string($ability) && str_starts_with($ability, self::SESSION_ABILITY_PREFIX)) {
                return substr($ability, strlen(self::SESSION_ABILITY_PREFIX));
            }
        }

        return null;
    }

    public function revokeSession(User $user, string $sessionId): void
    {
        $ability = self::SESSION_ABILITY_PREFIX.$sessionId;

        $user->tokens()
            ->whereIn('name', self::WEB_TOKEN_NAMES)
            ->get()
            ->each(function (PersonalAccessToken $token) use ($ability): void {
                if (in_array($ability, $token->abilities ?? [], true)) {
                    $token->delete();
                }
            });
    }

    public function revokeCurrentAccessAndSessionAccessTokens(User $user, string $sessionId): void
    {
        $ability = self::SESSION_ABILITY_PREFIX.$sessionId;

        $user->tokens()
            ->where('name', WebAuthService::ACCESS_TOKEN_NAME)
            ->get()
            ->each(function (PersonalAccessToken $token) use ($ability): void {
                if (in_array($ability, $token->abilities ?? [], true)) {
                    $token->delete();
                }
            });
    }

    /**
     * @deprecated Prefer revokeSession — kept for clarity in call sites that previously wiped all sessions.
     */
    public function revokeTokensForLogin(User $user, string $channel): void
    {
        // SPEC-010: login não revoga outras sessões.
    }

    public function revokeTokensForLogout(User $user, string $channel, ?string $sessionId = null): void
    {
        if ($channel !== self::CHANNEL_WEB) {
            return;
        }

        if ($sessionId !== null && $sessionId !== '') {
            $this->revokeSession($user, $sessionId);

            return;
        }

        // Fallback legado: sem session id, não apaga todas as sessões.
    }
}
