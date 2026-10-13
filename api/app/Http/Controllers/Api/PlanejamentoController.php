<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlanejamentoAnualRequest;
use App\Http\Requests\StorePlanejamentoEventoRequest;
use App\Http\Requests\TransitionPlanejamentoStatusRequest;
use App\Http\Requests\UpdatePlanejamentoAnualRequest;
use App\Http\Requests\UpdatePlanejamentoEventoRequest;
use App\Http\Resources\PlanejamentoAnualResource;
use App\Http\Resources\PlanejamentoEventoResource;
use App\Services\PlanejamentoAccessService;
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
        $mes = $request->filled('mes') ? (int) $request->query('mes') : null;

        return $this->planejamento->pdf($anual, $mes);
    }
}
