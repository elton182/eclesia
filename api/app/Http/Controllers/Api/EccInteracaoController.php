<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EccComentario;
use App\Models\User;
use App\Services\EccInteracaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EccInteracaoController extends Controller
{
    public function __construct(
        private readonly EccInteracaoService $interacoes,
    ) {}

    public function likeRecado(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->interacoes->like(EccComentario::ALVO_RECADO, $id, $user),
        ]);
    }

    public function unlikeRecado(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->interacoes->unlike(EccComentario::ALVO_RECADO, $id, $user),
        ]);
    }

    public function likeReflexao(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->interacoes->like(EccComentario::ALVO_REFLEXAO, $id, $user),
        ]);
    }

    public function unlikeReflexao(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->interacoes->unlike(EccComentario::ALVO_REFLEXAO, $id, $user),
        ]);
    }

    public function listComentariosRecado(Request $request, string $id): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->interacoes->listComentarios(EccComentario::ALVO_RECADO, $id, $user instanceof User ? $user : null),
        ]);
    }

    public function listComentariosReflexao(Request $request, string $id): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->interacoes->listComentarios(EccComentario::ALVO_REFLEXAO, $id, $user instanceof User ? $user : null),
        ]);
    }

    public function storeComentarioRecado(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $corpo = (string) $request->input('corpo', '');
        $comentario = $this->interacoes->createComentario(EccComentario::ALVO_RECADO, $id, $corpo, $user);

        return response()->json([
            'data' => $this->interacoes->serializeComentario($comentario),
        ], 201);
    }

    public function storeComentarioReflexao(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $corpo = (string) $request->input('corpo', '');
        $comentario = $this->interacoes->createComentario(EccComentario::ALVO_REFLEXAO, $id, $corpo, $user);

        return response()->json([
            'data' => $this->interacoes->serializeComentario($comentario),
        ], 201);
    }

    public function listModeracao(Request $request): JsonResponse
    {
        $filters = array_filter([
            'status' => $request->query('status'),
            'alvo_tipo' => $request->query('alvo_tipo'),
            'alvo_id' => $request->query('alvo_id'),
        ], static fn (mixed $v): bool => $v !== null && $v !== '');

        return response()->json([
            'data' => $this->interacoes->listComentariosModeracao($filters),
        ]);
    }

    public function aprovar(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->interacoes->serializeComentario(
                $this->interacoes->aprovar($id, $user)
            ),
        ]);
    }

    public function rejeitar(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $motivo = $request->input('motivo');

        return response()->json([
            'data' => $this->interacoes->serializeComentario(
                $this->interacoes->rejeitar($id, $user, is_string($motivo) ? $motivo : null)
            ),
        ]);
    }

    public function ocultar(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->interacoes->serializeComentario(
                $this->interacoes->ocultar($id, $user)
            ),
        ]);
    }

    public function destroyComentario(Request $request, string $id): Response
    {
        /** @var User $user */
        $user = $request->user();
        $this->interacoes->deleteComentario($id, $user);

        return response()->noContent();
    }
}
