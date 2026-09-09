<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Igreja;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Services\IgrejaAccessService;
use App\Services\IgrejaContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Define a igreja atual a partir do header X-Igreja (validado) ou da primeira acessível.
 */
class SetIgrejaFromHeader
{
    public function __construct(
        private readonly IgrejaContext $igrejaContext,
        private readonly IgrejaAccessService $access,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();

        if (! $actor instanceof SuperAdmin && ! $actor instanceof User) {
            return $next($request);
        }

        $header = $request->header('X-Igreja');
        $accessible = $this->access->accessibleIds($actor);

        if (is_string($header) && $header !== '') {
            if (! $accessible->contains($header)) {
                return response()->json([
                    'message' => 'Igreja inválida ou sem acesso para o usuário autenticado.',
                ], 403);
            }

            $igreja = Igreja::query()->find($header);
            if ($igreja === null) {
                return response()->json([
                    'message' => 'Igreja inválida ou sem acesso para o usuário autenticado.',
                ], 403);
            }

            $this->igrejaContext->set($igreja);
            setPermissionsTeamId($igreja->id);

            return $next($request);
        }

        $firstId = $accessible->first();
        if ($firstId !== null) {
            $igreja = Igreja::query()->find($firstId);
            if ($igreja !== null) {
                $this->igrejaContext->set($igreja);
                setPermissionsTeamId($igreja->id);
            }
        }

        return $next($request);
    }
}
