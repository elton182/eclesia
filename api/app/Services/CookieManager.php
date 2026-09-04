<?php

namespace App\Services;

use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

class CookieManager
{
    // Nomes dos cookies
    public const ACCESS_TOKEN_COOKIE = 'access_token';
    public const REFRESH_TOKEN_COOKIE = 'refresh_token';

    // Tempos de expiração em minutos
    public const ACCESS_TOKEN_EXPIRY = 10; // 10 minutos
    public const REFRESH_TOKEN_EXPIRY = 1440; // 1 dia (24 horas)

    /**
     * Cria um cookie de token com as configurações padrão de segurança
     *
     * @param string $name Nome do cookie
     * @param string $token Valor do token
     * @param int $minutes Tempo de expiração em minutos
     * @return SymfonyCookie
     */
    public function createTokenCookie(string $name, string $token, int $minutes): SymfonyCookie
    {
        return Cookie::make(
            $name,
            $token,
            $minutes,
            '/',      // path - importante para garantir que o cookie seja acessível em todo o site
            null,     // domain (null usa o domínio atual)
            config('app.env') === 'production',    // secure - false para desenvolvimento local e testes (HTTP), true em produção (HTTPS)
            config('app.env') === 'production',     // httpOnly - false para testes, true em produção para proteger contra XSS
            false,    // raw
            'lax'     // sameSite - 'lax' permite cookies em navegação normal, mas protege contra CSRF
        );
    }

    /**
     * Cria um cookie de access token
     *
     * @param string $token
     * @return SymfonyCookie
     */
    public function createAccessTokenCookie(string $token): SymfonyCookie
    {
        return $this->createTokenCookie(
            self::ACCESS_TOKEN_COOKIE,
            $token,
            self::ACCESS_TOKEN_EXPIRY
        );
    }

    /**
     * Cria um cookie de refresh token
     *
     * @param string $token
     * @return SymfonyCookie
     */
    public function createRefreshTokenCookie(string $token): SymfonyCookie
    {
        return $this->createTokenCookie(
            self::REFRESH_TOKEN_COOKIE,
            $token,
            self::REFRESH_TOKEN_EXPIRY
        );
    }

    /**
     * Remove o cookie de access token
     *
     * @return SymfonyCookie
     */
    public function forgetAccessTokenCookie(): SymfonyCookie
    {
        // Criar cookie com valor vazio e tempo expirado para garantir remoção
        return Cookie::make(
            self::ACCESS_TOKEN_COOKIE,
            '',
            -1,       // tempo negativo para expirar imediatamente
            '/',      // path
            null,     // domain
            config('app.env') === 'production',    // secure - mesma configuração do createTokenCookie
            config('app.env') === 'production',     // httpOnly - false para testes, true em produção
            false,    // raw
            'lax'     // sameSite
        );
    }

    /**
     * Remove o cookie de refresh token
     *
     * @return SymfonyCookie
     */
    public function forgetRefreshTokenCookie(): SymfonyCookie
    {
        // Criar cookie com valor vazio e tempo expirado para garantir remoção
        return Cookie::make(
            self::REFRESH_TOKEN_COOKIE,
            '',
            -1,       // tempo negativo para expirar imediatamente
            '/',      // path
            null,     // domain
            config('app.env') === 'production',    // secure - mesma configuração do createTokenCookie
            config('app.env') === 'production',     // httpOnly - false para testes, true em produção
            false,    // raw
            'lax'     // sameSite
        );
    }

    /**
     * Renova os cookies com os mesmos tokens mas com novos tempos de expiração
     *
     * @param string $accessToken
     * @param string $refreshToken
     * @return array Array com os cookies renovados
     */
    public function renewTokenCookies(string $accessToken, string $refreshToken): array
    {
        return [
            $this->createAccessTokenCookie($accessToken),
            $this->createRefreshTokenCookie($refreshToken)
        ];
    }

    /**
     * Obtém o access token do request
     *
     * @param \Illuminate\Http\Request $request
     * @return string|null
     */
    public function getAccessTokenFromRequest($request): ?string
    {
        // Primeiro tenta obter do cookie normal
        $token = $request->cookie(self::ACCESS_TOKEN_COOKIE);
        
        // Se não encontrar, tenta extrair do cabeçalho Cookie (útil para testes)
        if (!$token) {
            $token = $this->getTokenFromCookieHeader($request, self::ACCESS_TOKEN_COOKIE);
        }
        
        return $token;
    }

    /**
     * Obtém o refresh token do request
     *
     * @param \Illuminate\Http\Request $request
     * @return string|null
     */
    public function getRefreshTokenFromRequest($request): ?string
    {
        // Primeiro tenta obter do cookie normal
        $token = $request->cookie(self::REFRESH_TOKEN_COOKIE);
        
        // Se não encontrar, tenta extrair do cabeçalho Cookie (útil para testes)
        if (!$token) {
            $token = $this->getTokenFromCookieHeader($request, self::REFRESH_TOKEN_COOKIE);
        }
        
        return $token;
    }

    /**
     * Extrai um cookie específico do cabeçalho Cookie
     * Útil para testes quando $request->cookie() não funciona
     *
     * @param \Illuminate\Http\Request $request
     * @param string $cookieName
     * @return string|null
     */
    private function getTokenFromCookieHeader($request, string $cookieName): ?string
    {
        $cookieHeader = $request->header('Cookie');
        
        if (!$cookieHeader) {
            return null;
        }

        // Parse do cabeçalho Cookie: "name1=value1; name2=value2"
        $cookies = [];
        $parts = explode(';', $cookieHeader);
        
        foreach ($parts as $part) {
            $part = trim($part);
            if (strpos($part, '=') !== false) {
                [$name, $value] = explode('=', $part, 2);
                $cookies[trim($name)] = trim($value);
            }
        }

        return $cookies[$cookieName] ?? null;
    }
}