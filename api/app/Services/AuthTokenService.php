<?php

namespace App\Services;

use App\Models\User;

class AuthTokenService
{
    public const CHANNEL_WEB = 'web';

    public const WEB_TOKEN_NAMES = [
        WebAuthService::ACCESS_TOKEN_NAME,
        WebAuthService::REFRESH_TOKEN_NAME,
    ];

    public function revokeTokensForLogin(User $user, string $channel): void
    {
        if ($channel !== self::CHANNEL_WEB) {
            return;
        }

        $user->tokens()->whereIn('name', self::WEB_TOKEN_NAMES)->delete();
    }

    public function revokeTokensForLogout(User $user, string $channel): void
    {
        $this->revokeTokensForLogin($user, $channel);
    }
}
