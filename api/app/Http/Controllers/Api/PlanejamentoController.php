<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlanejamentoDisponibilidadeRequest;
use App\Http\Requests\StorePlanejamentoAnualRequest;
use App\Http\Requests\StorePlanejamentoEventoPublicoRequest;
use App\Http\Requests\UpdatePlanejamentoEventoPublicoRequest;
use App\Http\Requests\StorePlanejamentoEventoRequest;
use App\Http\Requests\TransitionPlanejamentoStatusRequest;
use App\Http\Requests\UpdatePlanejamentoAnualRequest;
use App\Http\Requests\UpdatePlanejamentoEventoRequest;
use App\Http\Resources\PlanejamentoAnualResource;
use App\Http\Resources\PlanejamentoEventoResource;
use App\Http\Resources\PlanejamentoPastoralTokenResource;
use App\Services\PlanejamentoAccessService;
use App\Services\PlanejamentoIntervalo;
use App\Services\PlanejamentoRecursoService;
use App\Services\PlanejamentoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PlanejamentoController extends Controller
{
    public function __construct(
        private readonly PlanejamentoService $planejamento,
        private readonly PlanejamentoAccessService $access,
        private readonly PlanejamentoRecursoService $recursos,
    ) {}

    public function indexAnuais(Request $request): AnonymousResourceCollection
    {
        abort_unless($this->access->canView(), 403);
        $ano = $request->filled('ano') ? (int) $request->query('ano') : null;

        return PlanejamentoAnualResource::collection($this->planejamento->listAnuais($ano));
    }

    public function storeAnual(StorePlanejamentoAnualRequest $request): JsonResponse
    {
        abort_unless($this->access->canGerir(), 403);

        $anual = $this->planejamento->createAnual($request->validated());

        return (new PlanejamentoAnualResource($anual))->response()->setStatusCode(201);
    }

    public function showAnual(string $id): PlanejamentoAnualResource
    {
        abort_unless($this->access->canView(), 403);

        return new PlanejamentoAnualResource($this->planejamento->findAnual($id));
    }

    public function updateAnual(UpdatePlanejamentoAnualRequest $request, string $id): PlanejamentoAnualResource
    {
        abort_unless($this->access->canGerir(), 403);

        return new PlanejamentoAnualResource(
            $this->planejamento->updateAnual($this->planejamento->findAnual($id), $request->validated())
        );
    }

    public function destroyAnual(string $id): Response
    {
        abort_unless($this->access->canGerir(), 403);
        $this->planejamento->deleteAnual($this->planejamento->findAnual($id));

        return response()->noContent();
    }

    public function transitionStatus(TransitionPlanejamentoStatusRequest $request, string $id): PlanejamentoAnualResource
    {
        abort_unless($this->access->canGerir(), 403);

        return new PlanejamentoAnualResource(
            $this->planejamento->transitionStatus(
                $this->planejamento->findAnual($id),
                $request->validated('status')
            )
        );
    }

    public function indexEventos(Request $request, string $id): AnonymousResourceCollection
    {
        abort_unless($this->access->canView(), 403);
        $anual = $this->planejamento->findAnual($id);

        return PlanejamentoEventoResource::collection(
            $this->planejamento->listEventos($anual, $request->only([
                'mes', 'pastoral_id', 'status_solicitacao', 'local_id',
            ]))
        );
    }

    public function storeEvento(StorePlanejamentoEventoRequest $request, string $id): JsonResponse
    {
        abort_unless($this->access->canView(), 403);
        $anual = $this->planejamento->findAnual($id);
        $data = $request->validated();

        abort_unless(
            $this->access->canCreateEvento($anual, $data['pastoral_id']),
            403
        );

        $result = $this->planejamento->createEvento($anual, $data);

        return (new PlanejamentoEventoResource($result['evento']))
            ->withConflitos($result['conflitos'])
            ->response()
            ->setStatusCode(201);
    }

    public function showEvento(string $eventoId): PlanejamentoEventoResource
    {
        abort_unless($this->access->canView(), 403);
        $evento = $this->planejamento->findEvento($eventoId);

        return new PlanejamentoEventoResource($evento);
    }

    public function updateEvento(UpdatePlanejamentoEventoRequest $request, string $eventoId): PlanejamentoEventoResource
    {
        abort_unless($this->access->canView(), 403);
        $evento = $this->planejamento->findEvento($eventoId);
        abort_unless($this->access->canEditEvento($evento), 403);

        $result = $this->planejamento->updateEvento($evento, $request->validated());

        return (new PlanejamentoEventoResource($result['evento']))
            ->withConflitos($result['conflitos']);
    }

    public function destroyEvento(string $eventoId): Response
    {
        abort_unless($this->access->canView(), 403);
        $evento = $this->planejamento->findEvento($eventoId);
        abort_unless($this->access->canDeleteEvento($evento), 403);
        $this->planejamento->deleteEvento($evento);

        return response()->noContent();
    }

    public function pdf(Request $request, string $id): SymfonyResponse
    {
        abort_unless($this->access->canView(), 403);
        $anual = $this->planejamento->findAnual($id);
        $tipo = (string) $request->query('tipo', 'agenda');
        if (! in_array($tipo, ['agenda', 'links'], true)) {
            $tipo = 'agenda';
        }
        $mes = $request->filled('mes') ? (int) $request->query('mes') : null;

        return $this->planejamento->pdf($anual, $tipo, $mes);
    }

    public function disponibilidade(PlanejamentoDisponibilidadeRequest $request, string $id): JsonResponse
    {
        abort_unless($this->access->canView(), 403);
        $anual = $this->planejamento->findAnual($id);
        $params = $request->validated();

        return response()->json([
            'data' => $this->recursos->disponibilidade(
                $anual->id,
                PlanejamentoIntervalo::deDados(
                    $params['data_inicio'],
                    $params['data_fim'] ?? null,
                    $params['hora_inicio'] ?? null,
                    $params['hora_fim'] ?? null,
                ),
                $params['ignorar_evento_id'] ?? null,
            ),
        ]);
    }

    public function storePastoralToken(string $id, string $pastoralId): JsonResponse
    {
        abort_unless($this->access->canGerir(), 403);
        $result = $this->planejamento->createOrRotatePastoralToken(
            $this->planejamento->findAnual($id),
            $pastoralId
        );

        return (new PlanejamentoPastoralTokenResource($result['token']))
            ->additional(['token' => $result['plain']])
            ->response()
            ->setStatusCode(201);
    }

    public function indexPastoralTokens(string $id): AnonymousResourceCollection
    {
        abort_unless($this->access->canGerir(), 403);

        return PlanejamentoPastoralTokenResource::collection(
            $this->planejamento->listPastoralTokens($this->planejamento->findAnual($id))
        );
    }

    public function destroyPastoralToken(string $id, string $pastoralId): Response
    {
        abort_unless($this->access->canGerir(), 403);
        $this->planejamento->revokePastoralToken(
            $this->planejamento->findAnual($id),
            $pastoralId
        );

        return response()->noContent();
    }

    public function showPublico(string $token): JsonResponse
    {
        $link = $this->planejamento->findTokenByPlain($token);
        $ano = $link->planejamento;

        return response()->json([
            'data' => [
                'ano' => $ano?->ano,
                'status' => $ano?->status,
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
        $result = $this->planejamento->createEventoPublico($link, $request->validated());

        return (new PlanejamentoEventoResource($result['evento']))
            ->withConflitos($result['conflitos'])
            ->response()
            ->setStatusCode(201);
    }

    public function updateEventoPublico(
        UpdatePlanejamentoEventoPublicoRequest $request,
        string $token,
        string $eventoId,
    ): PlanejamentoEventoResource {
        $link = $this->planejamento->findTokenByPlain($token);
        $result = $this->planejamento->updateEventoPublico($link, $eventoId, $request->validated());

        return (new PlanejamentoEventoResource($result['evento']))
            ->withConflitos($result['conflitos']);
    }

    public function destroyEventoPublico(string $token, string $eventoId): Response
    {
        $link = $this->planejamento->findTokenByPlain($token);
        $this->planejamento->deleteEventoPublico($link, $eventoId);

        return response()->noContent();
    }

    public function disponibilidadePublica(PlanejamentoDisponibilidadeRequest $request, string $token): JsonResponse
    {
        $link = $this->planejamento->findTokenByPlain($token);

        return response()->json([
            'data' => $this->planejamento->disponibilidadePublica($link, $request->validated()),
        ]);
    }
}
