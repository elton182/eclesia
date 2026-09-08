<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedByRequestDataException;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica API no contexto do tenant aceitando:
 * - SuperAdmin (token no banco central) — resolve auth ANTES de inicializar tenancy
 * - User do tenant (token no banco do tenant) — resolve auth DEPOIS
 *
 * Necessário porque a priority list do Laravel sobe auth:sanctum antes do tenancy,
 * e o inverso quebra o Bearer do super-admin (token só existe no central).
 */
class AuthenticateTenantApi
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Garante lookup de SuperAdmin/Tenant no banco central (request anterior pode
        // ter deixado tenancy inicializado no mesmo processo — testes/Octane).
        if ($this->tenancy->initialized) {
            $this->tenancy->end();
        }

        $slug = $request->header('X-Tenant') ?? $request->query('tenant');

        if (! is_string($slug) || $slug === '') {
            throw new TenantCouldNotBeIdentifiedByRequestDataException($slug ?? '');
        }

        $tenant = Tenant::query()->where('slug', $slug)->first();

        if ($tenant === null) {
            throw new TenantCouldNotBeIdentifiedByRequestDataException($slug);
        }

        $bearer = $request->bearerToken();
        $user = $this->alreadyAuthenticatedUser();

        if ($user === null && is_string($bearer) && $bearer !== '') {
            $user = $this->resolveSuperAdminFromCentralToken($bearer);
        }

        $this->tenancy->initialize($tenant);

        if ($user === null && is_string($bearer) && $bearer !== '') {
            $user = $this->resolveTenantUserFromToken($bearer);
        }

        // Sanctum::actingAs(User) em testes: usuário do tenant só é válido após tenancy
        if ($user === null) {
            $user = $this->alreadyAuthenticatedUser();
        }

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        Auth::guard('sanctum')->setUser($user);
        Auth::shouldUse('sanctum');
        $request->setUserResolver(static fn () => Auth::guard('sanctum')->user());

        return $next($request);
    }

    private function alreadyAuthenticatedUser(): SuperAdmin|User|null
    {
        $user = Auth::guard('sanctum')->user();

        if ($user instanceof SuperAdmin || $user instanceof User) {
            return $user;
        }

        return null;
    }

    private function resolveSuperAdminFromCentralToken(string $bearer): ?SuperAdmin
    {
        $accessToken = PersonalAccessToken::findToken($bearer);

        if ($accessToken === null) {
            return null;
        }

        $tokenable = $accessToken->tokenable;

        if (! $tokenable instanceof SuperAdmin) {
            return null;
        }

        if ($this->tokenExpired($accessToken)) {
            return null;
        }

        $tokenable->withAccessToken($accessToken);

        return $tokenable;
    }

    private function resolveTenantUserFromToken(string $bearer): ?User
    {
        $accessToken = PersonalAccessToken::findToken($bearer);

        if ($accessToken === null) {
            return null;
        }

        $tokenable = $accessToken->tokenable;

        if (! $tokenable instanceof User) {
            return null;
        }

        if ($this->tokenExpired($accessToken)) {
            return null;
        }

        if (! $tokenable->is_active) {
            return null;
        }

        $tokenable->withAccessToken($accessToken);

        return $tokenable;
    }

    private function tokenExpired(PersonalAccessToken $accessToken): bool
    {
        if (is_null($accessToken->expires_at)) {
            return false;
        }

        return $accessToken->expires_at->isPast();
    }
}
