<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;

class WebAuthService
{
    public const ACCESS_TOKEN_NAME = 'access-token';
    public const REFRESH_TOKEN_NAME = 'refresh-token';

    public function __construct(
        protected AuthTokenService $authTokenService
    ) {
    }

    public function generateAccessTokens(User $user, int $accessExpiry = 10, int $refreshExpiry = 1440): array
    {
        $this->authTokenService->revokeTokensForLogin($user, AuthTokenService::CHANNEL_WEB);

        $accessToken = $user->createToken(
            self::ACCESS_TOKEN_NAME,
            ['*'],
            Carbon::now()->addMinutes($accessExpiry)
        );

        $refreshToken = $user->createToken(
            self::REFRESH_TOKEN_NAME,
            ['*'],
            Carbon::now()->addMinutes($refreshExpiry)
        );

        return [
            'access' => $accessToken->plainTextToken,
            'refresh' => $refreshToken->plainTextToken,
        ];
    }
}
