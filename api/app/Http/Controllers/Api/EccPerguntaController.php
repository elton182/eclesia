<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResponderEccPerguntaEnvioRequest;
use App\Http\Requests\StoreEccPerguntaEnvioRequest;
use App\Http\Requests\StoreEccPerguntaRequest;
use App\Http\Requests\UpdateEccPerguntaRequest;
use App\Models\EccPerguntaEnvio;
use App\Models\User;
use App\Services\EccPerguntaService;
use App\Services\EccVisibilityScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EccPerguntaController extends Controller
{
    public function __construct(
        private readonly EccPerguntaService $perguntas,
        private readonly EccVisibilityScope $visibility,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = array_filter([
            'status' => $request->query('status'),
        ], static fn (mixed $v): bool => $v !== null && $v !== '');

        return response()->json(['data' => $this->perguntas->list($filters)]);
    }

    public function resumo(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->perguntas->resumoLider()]);
    }

    public function show(string $id): JsonResponse
    {
        $pergunta = $this->perguntas->find($id);
        /** @var User|null $user */
        $user = auth()->user() instanceof User ? auth()->user() : null;
        $equipeIds = $user ? ($this->visibility->restrictedEquipeIds($user) ?? []) : [];

        // Líder que abre o detalhe com resposta já disponível deixa de ver o badge.
        if (
            $user
            && $equipeIds !== []
            && ! $this->visibility->userCan('ecc.perguntas.manage')
        ) {
            $this->perguntas->marcarRespostasVistas($pergunta, $user, $equipeIds);
            $pergunta->unsetRelation('envios');
        }

        return response()->json([
            'data' => $this->perguntas->serialize($pergunta, $user, $equipeIds, listMode: false),
        ]);
    }

    public function store(StoreEccPerguntaRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $pergunta = $this->perguntas->create($request->validated(), $user);

        return response()->json([
            'data' => $this->perguntas->serialize($pergunta, $user, listMode: false),
        ], 201);
    }

    public function update(UpdateEccPerguntaRequest $request, string $id): JsonResponse
    {
        $pergunta = $this->perguntas->update($this->perguntas->find($id), $request->validated());
        /** @var User|null $user */
        $user = $request->user() instanceof User ? $request->user() : null;

        return response()->json([
            'data' => $this->perguntas->serialize($pergunta, $user, listMode: false),
        ]);
    }

    public function destroy(string $id): Response
    {
        $this->perguntas->delete($this->perguntas->find($id));

        return response()->noContent();
    }

    public function disponibilizar(string $id): JsonResponse
    {
        $pergunta = $this->perguntas->disponibilizar($this->perguntas->find($id));
        /** @var User|null $user */
        $user = auth()->user() instanceof User ? auth()->user() : null;

        return response()->json([
            'data' => $this->perguntas->serialize($pergunta, $user, listMode: false),
        ]);
    }

    public function arquivar(string $id): JsonResponse
    {
        $pergunta = $this->perguntas->arquivar($this->perguntas->find($id));
        /** @var User|null $user */
        $user = auth()->user() instanceof User ? auth()->user() : null;

        return response()->json([
            'data' => $this->perguntas->serialize($pergunta, $user, listMode: false),
        ]);
    }

    public function storeEnvio(StoreEccPerguntaEnvioRequest $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $envio = $this->perguntas->enviar(
            $this->perguntas->find($id),
            $request->validated(),
            $user,
        );

        return response()->json([
            'data' => $this->perguntas->serializeEnvio($envio),
        ], 201);
    }

    public function listEnvios(string $id): JsonResponse
    {
        return response()->json([
            'data' => $this->perguntas->listEnvios($this->perguntas->find($id)),
        ]);
    }

    public function responder(ResponderEccPerguntaEnvioRequest $request, string $envioId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $envio = EccPerguntaEnvio::query()->whereKey($envioId)->firstOrFail();
        $atualizado = $this->perguntas->responder($envio, $request->validated(), $user);

        return response()->json([
            'data' => $this->perguntas->serializeEnvio($atualizado),
        ]);
    }
}
