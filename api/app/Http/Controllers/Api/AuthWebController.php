<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuthRequest;
use App\Models\User;
use App\Services\AuthTokenService;
use App\Services\CookieManager;
use App\Services\WebAuthService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthWebController extends Controller
{
    public function __construct(
        protected CookieManager $cookieManager,
        protected WebAuthService $webAuthService,
        protected AuthTokenService $authTokenService
    ) {
    }

    public function auth(AuthRequest $request)
    {
        $data = $request->only(['email', 'password']);

        // Login com e-mail criptografado (LGPD): Auth::attempt não descriptografa.
        $user = User::whereEncrypted('email', $data['email'])->first();

        if ($user && Hash::check($data['password'], $user->password)) {
            Auth::login($user);

            $tokens = $this->webAuthService->generateAccessTokens(
                $user,
                CookieManager::ACCESS_TOKEN_EXPIRY,
                CookieManager::REFRESH_TOKEN_EXPIRY
            );

            return response()->json(['success' => true, 'message' => 'Login efetuado com sucesso!'])
                ->withCookie($this->cookieManager->createAccessTokenCookie($tokens['access']))
                ->withCookie($this->cookieManager->createRefreshTokenCookie($tokens['refresh']));
        }

        return response()->json([
            'message' => 'E-mail ou senha incorretos.',
        ], 403);
    }

    public function me()
    {
        $user = Auth::user();

        if (!$user) {
            $user = auth('sanctum')->user();
        }

        if (!$user) {
            return response()->json(['message' => 'Usuário não autenticado', 'success' => false], 401);
        }

        return response()->json($user);
    }

    public function logout()
    {
        $user = Auth::user() ?? auth('sanctum')->user();

        if ($user) {
            $this->authTokenService->revokeTokensForLogout($user, AuthTokenService::CHANNEL_WEB);
        }

        Auth::logout();

        return response()->json(['message' => 'Logout efetuado.'])
            ->withCookie($this->cookieManager->forgetAccessTokenCookie())
            ->withCookie($this->cookieManager->forgetRefreshTokenCookie());
    }
}
