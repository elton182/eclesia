<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\TenantResolutionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AuthRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthTokenService;
use App\Services\CookieManager;
use App\Services\IgrejaContext;
use App\Services\TenantResolver;
use App\Services\UserService;
use App\Services\WebAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Stancl\Tenancy\Tenancy;

class AuthWebController extends Controller
{
    public function __construct(
        protected CookieManager $cookieManager,
        protected WebAuthService $webAuthService,
        protected AuthTokenService $authTokenService,
        protected TenantResolver $tenantResolver,
        protected Tenancy $tenancy,
        protected UserService $userService,
        protected IgrejaContext $igrejaContext,
    ) {}

    public function auth(AuthRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $tenant = $this->tenantResolver->resolve($data['tenant']);
        } catch (TenantResolutionException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 404);
        }

        $this->tenancy->initialize($tenant);

        $user = User::findByEmail($data['email']);

        if ($user && $user->is_active && Hash::check($data['password'], $user->password)) {
            // Não usar Auth::login (sessão web): User vive no DB do tenant e o
            // Sanctum stateful tentaria carregar `users` na conexão central.
            $this->forgetWebSession($request);

            $tokens = $this->webAuthService->generateAccessTokens(
                $user,
                CookieManager::ACCESS_TOKEN_EXPIRY,
                CookieManager::REFRESH_TOKEN_EXPIRY
            );

            return response()->json([
                'success' => true,
                'message' => 'Login efetuado com sucesso!',
                'access_token' => $tokens['access'],
                'tenant' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                ],
                'user' => (new UserResource($user))->resolve(),
            ])
                ->withCookie($this->cookieManager->createAccessTokenCookie($tokens['access']))
                ->withCookie($this->cookieManager->createRefreshTokenCookie($tokens['refresh']));
        }

        return response()->json([
            'message' => 'E-mail ou senha incorretos.',
        ], 403);
    }

    public function me(): JsonResponse
    {
        $user = auth('sanctum')->user();

        if (! $user instanceof User) {
            return response()->json(['message' => 'Usuário não autenticado', 'success' => false], 401);
        }

        $igrejaId = null;
        try {
            $igrejaId = $this->igrejaContext->current()->id;
            setPermissionsTeamId($igrejaId);
        } catch (\Throwable) {
            setPermissionsTeamId(null);
        }

        $permissions = $user->getAllPermissions()->pluck('name')->values()->all();

        setPermissionsTeamId(null);
        if ($user->hasRole('admin-tenant')) {
            $permissions = Permission::query()->where('guard_name', 'web')->pluck('name')->values()->all();
        } elseif ($user->hasRole('gestor-site')) {
            $orgPermissions = $user->getAllPermissions()->pluck('name')->values()->all();
            $permissions = array_values(array_unique([...$permissions, ...$orgPermissions]));
        }

        return response()->json([
            'success' => true,
            ...(new UserResource($user))->resolve(),
            'permissions' => $permissions,
            'igreja_id' => $igrejaId,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = auth('sanctum')->user();

        if ($user) {
            $this->authTokenService->revokeTokensForLogout($user, AuthTokenService::CHANNEL_WEB);
        }

        $this->forgetWebSession($request);

        return response()->json(['message' => 'Logout efetuado.'])
            ->withCookie($this->cookieManager->forgetAccessTokenCookie())
            ->withCookie($this->cookieManager->forgetRefreshTokenCookie());
    }

    /**
     * Renova o access token a partir do cookie de refresh (ou body).
     */
    public function refresh(Request $request): JsonResponse
    {
        $refreshPlain = $this->cookieManager->getRefreshTokenFromRequest($request);
        if (! is_string($refreshPlain) || $refreshPlain === '') {
            $refreshPlain = $request->input('refresh_token');
        }

        if (! is_string($refreshPlain) || $refreshPlain === '' || ! str_contains($refreshPlain, '|')) {
            return response()->json(['message' => 'Sessão expirada. Faça login novamente.'], 401);
        }

        [$id, $secret] = explode('|', $refreshPlain, 2);
        if (! is_numeric($id) || $secret === '') {
            return response()->json(['message' => 'Sessão expirada. Faça login novamente.'], 401);
        }

        $record = \Laravel\Sanctum\PersonalAccessToken::query()->find($id);
        if (
            $record === null
            || $record->name !== WebAuthService::REFRESH_TOKEN_NAME
            || ! hash_equals($record->token, hash('sha256', $secret))
        ) {
            return response()->json(['message' => 'Sessão expirada. Faça login novamente.'], 401);
        }

        if ($record->expires_at !== null && $record->expires_at->isPast()) {
            return response()->json(['message' => 'Sessão expirada. Faça login novamente.'], 401);
        }

        $user = $record->tokenable;
        if (! $user instanceof User) {
            return response()->json(['message' => 'Sessão expirada. Faça login novamente.'], 401);
        }

        $user->tokens()->where('name', WebAuthService::ACCESS_TOKEN_NAME)->delete();

        $accessToken = $user->createToken(
            WebAuthService::ACCESS_TOKEN_NAME,
            ['*'],
            now()->addMinutes(CookieManager::ACCESS_TOKEN_EXPIRY)
        );

        $record->forceFill(['last_used_at' => now()])->save();

        $plainAccess = $accessToken->plainTextToken;

        return response()->json([
            'success' => true,
            'access_token' => $plainAccess,
        ])
            ->withCookie($this->cookieManager->createAccessTokenCookie($plainAccess))
            ->withCookie($this->cookieManager->createRefreshTokenCookie($refreshPlain));
    }

    private function forgetWebSession(Request $request): void
    {
        // Não chamar Auth::logout(): ele hidrata User e quebra no banco central.
        if ($request->hasSession()) {
            $loginKey = Auth::guard('web')->getName();
            $request->session()->forget($loginKey);
            $request->session()->forget('password_hash_'.$loginKey);
            $request->session()->forget('password_hash_web');
        }
    }
}
