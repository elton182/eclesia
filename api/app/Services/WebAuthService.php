<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use DateTimeInterface;

class WebAuthService
{
    public const ACCESS_TOKEN_NAME = 'access-token';

    public const REFRESH_TOKEN_NAME = 'refresh-token';

    public function __construct(
        protected AuthTokenService $authTokenService
    ) {}

    /**
     * @return array{access: string, refresh: string, session_id: string}
     */
    public function generateAccessTokens(
        User $user,
        int $accessExpiry = CookieManager::ACCESS_TOKEN_EXPIRY,
        ?int $refreshExpiry = CookieManager::REFRESH_TOKEN_EXPIRY
    ): array {
        $sessionId = $this->authTokenService->newSessionId();
        $abilities = $this->authTokenService->abilitiesForSession($sessionId);

        $accessToken = $user->createToken(
            self::ACCESS_TOKEN_NAME,
            $abilities,
            Carbon::now()->addMinutes($accessExpiry)
        );

        /** @var DateTimeInterface|null $refreshExpiresAt */
        $refreshExpiresAt = $refreshExpiry === null
            ? null
            : Carbon::now()->addMinutes($refreshExpiry);

        $refreshToken = $user->createToken(
            self::REFRESH_TOKEN_NAME,
            $abilities,
            $refreshExpiresAt
        );

        return [
            'access' => $accessToken->plainTextToken,
            'refresh' => $refreshToken->plainTextToken,
            'session_id' => $sessionId,
        ];
    }
}
