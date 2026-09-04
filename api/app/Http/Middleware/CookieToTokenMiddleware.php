<?php

namespace App\Http\Middleware;

use App\Services\CookieManager;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class CookieToTokenMiddleware
{
    protected CookieManager $cookieManager;

    public function __construct(CookieManager $cookieManager)
    {
        $this->cookieManager = $cookieManager;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Verificar se há Authorization header
        if (!$request->hasHeader('Authorization')) {
            $accessToken = $this->cookieManager->getAccessTokenFromRequest($request);
            $refreshToken = $this->cookieManager->getRefreshTokenFromRequest($request);

            $cookiesToAdd = [];

            if ($accessToken) {
                $validToken = $this->checkAccessToken($accessToken, $refreshToken);

                if ($validToken) {
                    $request->headers->set('Authorization', 'Bearer ' . $validToken);

                    // Renovar cookies com os mesmos tokens mas tempo de validade atualizado
                    $cookiesToAdd[] = $this->cookieManager->createAccessTokenCookie($accessToken);
                    if ($refreshToken) {
                        $cookiesToAdd[] = $this->cookieManager->createRefreshTokenCookie($refreshToken);
                    }
                }
            } else if ($refreshToken) {
                // Se não há access token mas há refresh token, tentar criar novo access token
                $newAccessToken = $this->createNewAccessToken($refreshToken);

                if ($newAccessToken) {
                    $request->headers->set('Authorization', 'Bearer ' . $newAccessToken);

                    // Adicionar os cookies com os tokens
                    $cookiesToAdd[] = $this->cookieManager->createAccessTokenCookie($newAccessToken);
                    $cookiesToAdd[] = $this->cookieManager->createRefreshTokenCookie($refreshToken);
                }
            }

            // Se temos cookies para adicionar, adiciona-los na resposta
            if (!empty($cookiesToAdd)) {

                $response = $next($request);
                foreach ($cookiesToAdd as $cookie) {
                    $response->headers->setCookie($cookie);
                }
                return $response;
            }
        }

        return $next($request);
    }

    private function getTokenRecord(?string $plainToken, string $name = null)
    {
        if (!$plainToken || !str_contains($plainToken, '|')) {
            return null;
        }

        try {
            // Sanctum armazena o hash do token, não o texto plano
            [$id, $token] = explode('|', $plainToken, 2);

            if (!is_numeric($id) || empty($token)) {
                return null;
            }

            $query = PersonalAccessToken::where('id', $id);

            if ($name) {
                $query->where('name', $name);
            }

            $tokenRecord = $query->first();

            // Verificar se o hash do token corresponde
            if ($tokenRecord && hash_equals($tokenRecord->token, hash('sha256', $token))) {
                return $tokenRecord;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('[CookieToToken] Erro ao buscar token record: ' . $e->getMessage());
            return null;
        }
    }

    private function checkAccessToken($accessToken, $refreshToken)
    {
        try {
            $now = Carbon::now();
            $tokenRecord = $this->getTokenRecord($accessToken, 'access-token');

            if (!$tokenRecord) {
                return null;
            }

            // Verificar se o token expirou
            if ($tokenRecord->last_used_at && now()->gt($tokenRecord->last_used_at->addMinutes(10))) {
                // Tentar renovar com refresh token
                return $this->checkRefreshToken($refreshToken, $accessToken);
            }

            // Atualizar last_used_at e renovar expiração do access token
            $tokenRecord->update([
                'last_used_at' => $now,
            ]);

            // Também renovar o refresh token se existir
            if ($refreshToken) {
                $refreshTokenRecord = $this->getTokenRecord($refreshToken, 'refresh-token');
                if ($refreshTokenRecord) {
                    $refreshTokenRecord->update([
                        'last_used_at' => $now,
                    ]);
                }
            }

            return $accessToken; // Retornar o token original (plain text)

        } catch (\Exception $e) {
            return null;
        }
    }

    private function checkRefreshToken($refreshToken, $accessToken)
    {
        try {
            if (!$refreshToken) {
                return null;
            }

            $now = Carbon::now();
            $refreshTokenRecord = $this->getTokenRecord($refreshToken, 'refresh-token');

            if (!$refreshTokenRecord) {
                return null;
            }

            if ($refreshTokenRecord->last_used_at && now()->gt($refreshTokenRecord->last_used_at->addDays(1))) {
                return null;
            }

            // Buscar o access token record para atualizar
            $accessTokenRecord = $this->getTokenRecord($accessToken, 'access-token');

            if (!$accessTokenRecord) {
                return null;
            }

            // Renovar o access token
            $accessTokenRecord->update([
                'last_used_at' => $now,
            ]);

            // Atualizar o refresh token também
            $refreshTokenRecord->update([
                'last_used_at' => $now,
            ]);

            // IMPORTANTE: Retornar o access token original após renovação
            return $accessToken;

        } catch (\Exception $e) {
            return null;
        }
    }

    private function createNewAccessToken($refreshToken)
    {
        try {

            $now = Carbon::now();
            $refreshTokenRecord = $this->getTokenRecord($refreshToken, 'refresh-token');

            if (!$refreshToken || !str_contains($refreshToken, '|') ||
                !$refreshTokenRecord ||
                ($refreshTokenRecord->last_used_at && now()->gt($refreshTokenRecord->last_used_at->addDays(1)))) {
                return null;
            }

            // Obter o usuário associado ao refresh token
            $user = $refreshTokenRecord->tokenable;

            if (!$user) {
                return null;
            }

            // Criar novo access token
            $newAccessToken = $user->createToken(
                'access-token',
                ['*'],
                $now->copy()->addMinutes(CookieManager::ACCESS_TOKEN_EXPIRY)
            );

            // Atualizar o refresh token
            $refreshTokenRecord->update([
                'last_used_at' => $now,
            ]);

            return $newAccessToken->plainTextToken;

        } catch (\Exception $e) {
            Log::error('[CookieToToken] Erro ao criar novo access token: ' . $e->getMessage());
            return null;
        }
    }
}
