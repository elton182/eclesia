<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SPEC-021: bloqueia API autenticada enquanto must_change_password estiver ativo.
 * Rotas permitidas: web/me, web/change-password, web/logout, web/refresh.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->must_change_password) {
            return $next($request);
        }

        if ($this->isAllowed($request)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'É necessário alterar a senha antes de continuar.',
            'code' => 'must_change_password',
        ], 403);
    }

    private function isAllowed(Request $request): bool
    {
        $path = trim($request->path(), '/');

        return in_array($path, [
            'api/v1/web/me',
            'api/v1/web/change-password',
            'api/v1/web/logout',
            'api/v1/web/refresh',
        ], true);
    }
}
