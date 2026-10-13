<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeletePlanejamentoPastoralUserRequest;
use App\Http\Requests\PatchPlanejamentoEventoRequest;
use App\Http\Requests\StorePlanejamentoAnualRequest;
use App\Http\Requests\StorePlanejamentoEventoPublicoRequest;
use App\Http\Requests\StorePlanejamentoEventoRequest;
use App\Http\Requests\StorePlanejamentoPastoralUserRequest;
use App\Http\Requests\TransitionPlanejamentoStatusRequest;
use App\Http\Requests\UpdatePlanejamentoAnualRequest;
use App\Http\Requests\UpdatePlanejamentoEventoPublicoRequest;
use App\Http\Requests\UpdatePlanejamentoEventoRequest;
use App\Http\Resources\PlanejamentoAnualResource;
use App\Http\Resources\PlanejamentoEventoResource;
use App\Http\Resources\PlanejamentoPastoralTokenResource;
use App\Http\Resources\UserResource;
use App\Services\PlanejamentoAccessService;
use App\Services\PlanejamentoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PlanejamentoController extends Controller
{
    public function __construct(
        private readonly PlanejamentoService $planejamento,
        private readonly PlanejamentoAccessService $access,
    ) {}

    public function indexAnos(): AnonymousResourceCollection
    {
        abort_unless($this->access->canView(), 403);

        return PlanejamentoAnualResource::collection($this->planejamento->listAnos());
    }

    public function storeAno(StorePlanejamentoAnualRequest $request): JsonResponse
    {
        abort_unless($this->access->canManage(), 403);
        $ano = $this->planejamento->createAno($request->validated());

        return (new PlanejamentoAnualResource($ano))->response()->setStatusCode(201);
    }

    public function showAno(string $id): PlanejamentoAnualResource
    {
        abort_unless($this->access->canView(), 403);

        return new PlanejamentoAnualResource($this->planejamento->findAno($id));
    }

    public function updateAno(UpdatePlanejamentoAnualRequest $request, string $id): PlanejamentoAnualResource
    {
        abort_unless($this->access->canManage(), 403);

        return new PlanejamentoAnualResource(
            $this->planejamento->updateAno($this->planejamento->findAno($id))
        );
    }

    public function destroyAno(string $id): Response
    {
        abort_unless($this->access->canManage(), 403);
        $this->planejamento->deleteAno($this->planejamento->findAno($id));

        return response()->noContent();
    }

    public function transitionStatus(TransitionPlanejamentoStatusRequest $request, string $id): PlanejamentoAnualResource
    {
        abort_unless($this->access->canManage(), 403);

        return new PlanejamentoAnualResource(
            $this->planejamento->transitionStatus(
                $this->planejamento->findAno($id),
                $request->validated()['status']
            )
        );
    }

    public function indexEventos(Request $request, string $id): AnonymousResourceCollection
    {
        abort_unless($this->access->canView(), 403);

        $filters = $request->only(['pastoral_id', 'status', 'from', 'to']);

        return PlanejamentoEventoResource::collection(
            $this->planejamento->listEventos(
                $this->planejamento->findAno($id),
                $filters,
                auth()->user()
            )
        );
    }

    public function storeEvento(StorePlanejamentoEventoRequest $request, string $id): JsonResponse
    {
        abort_unless($this->access->canManage() || $this->access->canPropose(), 403);
        $evento = $this->planejamento->createEvento(
            $this->planejamento->findAno($id),
            $request->validated(),
            auth()->user()
        );

        return (new PlanejamentoEventoResource($evento))->response()->setStatusCode(201);
    }

    public function showEvento(string $eventoId): PlanejamentoEventoResource
    {
        abort_unless($this->access->canView(), 403);
        $evento = $this->planejamento->findEvento($eventoId);

        if (! $this->access->canManage() && ! $this->access->isLinkedToPastoral($evento->pastoral_id)) {
            abort_unless($this->access->userCan('telas.planejamento'), 403);
        }

        return new PlanejamentoEventoResource($evento);
    }

    public function updateEvento(UpdatePlanejamentoEventoRequest $request, string $eventoId): PlanejamentoEventoResource
    {
        abort_unless($this->access->canManage() || $this->access->canPropose(), 403);

        return new PlanejamentoEventoResource(
            $this->planejamento->updateEvento(
                $this->planejamento->findEvento($eventoId),
                $request->validated(),
                auth()->user()
            )
        );
    }

    public function patchEvento(PatchPlanejamentoEventoRequest $request, string $eventoId): PlanejamentoEventoResource
    {
        abort_unless($this->access->canManage(), 403);

        return new PlanejamentoEventoResource(
            $this->planejamento->patchEvento(
                $this->planejamento->findEvento($eventoId),
                $request->validated(),
                auth()->user()
            )
        );
    }

    public function destroyEvento(string $eventoId): Response
    {
        abort_unless($this->access->canManage() || $this->access->canPropose(), 403);
        $this->planejamento->deleteEvento(
            $this->planejamento->findEvento($eventoId),
            auth()->user()
        );

        return response()->noContent();
    }

    public function storeToken(string $id, string $pastoralId): JsonResponse
    {
        abort_unless($this->access->canManage(), 403);
        $result = $this->planejamento->createOrRotateToken(
            $this->planejamento->findAno($id),
            $pastoralId
        );

        return response()->json([
            'data' => (new PlanejamentoPastoralTokenResource($result['tokenModel'], $result['token']))->resolve(),
            'token' => $result['token'],
        ], 201);
    }

    public function destroyToken(string $id, string $pastoralId): Response
    {
        abort_unless($this->access->canManage(), 403);
        $this->planejamento->revokeToken($this->planejamento->findAno($id), $pastoralId);

        return response()->noContent();
    }

    public function indexPastoralUsers(string $pastoralId): AnonymousResourceCollection
    {
        abort_unless($this->access->canManage(), 403);

        return UserResource::collection($this->planejamento->listPastoralUsers($pastoralId));
    }

    public function storePastoralUser(StorePlanejamentoPastoralUserRequest $request, string $pastoralId): JsonResponse
    {
        abort_unless($this->access->canManage(), 403);
        $user = $this->planejamento->attachPastoralUser($pastoralId, $request->validated());

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function destroyPastoralUser(DeletePlanejamentoPastoralUserRequest $request, string $pastoralId): Response
    {
        abort_unless($this->access->canManage(), 403);
        $this->planejamento->detachPastoralUser($pastoralId, $request->validated());

        return response()->noContent();
    }

    public function showPublico(string $token): JsonResponse
    {
        $link = $this->planejamento->findTokenByPlain($token);
        $ano = $link->planejamento;

        return response()->json([
            'data' => [
                'ano' => $ano->ano,
                'status' => $ano->status,
                'pastoral_id' => $link->pastoral_id,
                'pastoral_nome' => $link->pastoral?->nome,
            ],
        ]);
    }

    public function indexEventosPublicos(string $token): AnonymousResourceCollection
    {
        $link = $this->planejamento->findTokenByPlain($token);

        return PlanejamentoEventoResource::collection(
            $this->planejamento->listEventosPublicos($link)
        );
    }

    public function storeEventoPublico(StorePlanejamentoEventoPublicoRequest $request, string $token): JsonResponse
    {
        $link = $this->planejamento->findTokenByPlain($token);
        $evento = $this->planejamento->createEventoPublico($link, $request->validated());

        return (new PlanejamentoEventoResource($evento))->response()->setStatusCode(201);
    }

    public function updateEventoPublico(
        UpdatePlanejamentoEventoPublicoRequest $request,
        string $token,
        string $eventoId
    ): PlanejamentoEventoResource {
        $link = $this->planejamento->findTokenByPlain($token);
        $evento = $this->planejamento->findEventoPublico($link, $eventoId);

        return new PlanejamentoEventoResource(
            $this->planejamento->updateEventoPublico($link, $evento, $request->validated())
        );
    }

    public function destroyEventoPublico(string $token, string $eventoId): Response
    {
        $link = $this->planejamento->findTokenByPlain($token);
        $evento = $this->planejamento->findEventoPublico($link, $eventoId);
        $this->planejamento->deleteEventoPublico($link, $evento);

        return response()->noContent();
    }
}
