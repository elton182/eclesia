<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GerarEscalaOcorrenciasRequest;
use App\Http\Requests\StoreEscalaAtribuicaoRequest;
use App\Http\Requests\StoreEscalaEquipeRequest;
use App\Http\Requests\StoreEscalaOcorrenciaRequest;
use App\Http\Requests\StoreEscalaTipoRequest;
use App\Http\Requests\UpdateEscalaEquipeRequest;
use App\Http\Requests\UpdateEscalaOcorrenciaRequest;
use App\Http\Requests\UpdateEscalaTipoRequest;
use App\Http\Resources\EscalaAtribuicaoResource;
use App\Http\Resources\EscalaEquipeResource;
use App\Http\Resources\EscalaOcorrenciaResource;
use App\Http\Resources\EscalaTipoResource;
use App\Services\EscalaAccessService;
use App\Services\EscalaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class EscalaController extends Controller
{
    public function __construct(
        private readonly EscalaService $escalas,
        private readonly EscalaAccessService $access,
    ) {}

    public function indexTipos(): AnonymousResourceCollection
    {
        abort_unless($this->access->canView(), 403);

        return EscalaTipoResource::collection($this->escalas->listTipos());
    }

    public function storeTipo(StoreEscalaTipoRequest $request): JsonResponse
    {
        $tipo = $this->escalas->createTipo($request->validated());

        return (new EscalaTipoResource($tipo->loadCount(['equipes', 'ocorrencias'])))
            ->response()
            ->setStatusCode(201);
    }

    public function showTipo(string $id): EscalaTipoResource
    {
        abort_unless($this->access->canView(), 403);

        return new EscalaTipoResource($this->escalas->findTipo($id));
    }

    public function updateTipo(UpdateEscalaTipoRequest $request, string $id): EscalaTipoResource
    {
        $tipo = $this->escalas->findTipo($id);

        return new EscalaTipoResource($this->escalas->updateTipo($tipo, $request->validated()));
    }

    public function destroyTipo(string $id): Response
    {
        abort_unless($this->access->canManage(), 403);
        $this->escalas->deleteTipo($this->escalas->findTipo($id));

        return response()->noContent();
    }

    public function indexEquipes(string $tipoId): AnonymousResourceCollection
    {
        abort_unless($this->access->canView(), 403);

        return EscalaEquipeResource::collection($this->escalas->listEquipes($tipoId));
    }

    public function storeEquipe(StoreEscalaEquipeRequest $request, string $tipoId): JsonResponse
    {
        $equipe = $this->escalas->createEquipe($tipoId, $request->validated());

        return (new EscalaEquipeResource($equipe))->response()->setStatusCode(201);
    }

    public function updateEquipe(UpdateEscalaEquipeRequest $request, string $id): EscalaEquipeResource
    {
        $equipe = $this->escalas->findEquipe($id);

        return new EscalaEquipeResource($this->escalas->updateEquipe($equipe, $request->validated()));
    }

    public function destroyEquipe(string $id): Response
    {
        abort_unless($this->access->canManage(), 403);
        $this->escalas->deleteEquipe($this->escalas->findEquipe($id));

        return response()->noContent();
    }

    public function indexOcorrencias(string $tipoId): AnonymousResourceCollection
    {
        abort_unless($this->access->canView(), 403);

        return EscalaOcorrenciaResource::collection($this->escalas->listOcorrencias($tipoId));
    }

    public function storeOcorrencia(StoreEscalaOcorrenciaRequest $request, string $tipoId): JsonResponse
    {
        $ocorrencia = $this->escalas->createOcorrencia($tipoId, $request->validated());

        return (new EscalaOcorrenciaResource($ocorrencia))->response()->setStatusCode(201);
    }

    public function gerarOcorrencias(GerarEscalaOcorrenciasRequest $request, string $tipoId): JsonResponse
    {
        $items = $this->escalas->gerarOcorrencias($tipoId, $request->validated());

        return EscalaOcorrenciaResource::collection($items)
            ->response()
            ->setStatusCode(201);
    }

    public function showOcorrencia(string $id): EscalaOcorrenciaResource
    {
        abort_unless($this->access->canView(), 403);

        return new EscalaOcorrenciaResource($this->escalas->findOcorrencia($id));
    }

    public function updateOcorrencia(UpdateEscalaOcorrenciaRequest $request, string $id): EscalaOcorrenciaResource
    {
        $ocorrencia = $this->escalas->findOcorrencia($id);

        return new EscalaOcorrenciaResource(
            $this->escalas->updateOcorrencia($ocorrencia, $request->validated())
        );
    }

    public function destroyOcorrencia(string $id): Response
    {
        abort_unless($this->access->canManage(), 403);
        $this->escalas->deleteOcorrencia($this->escalas->findOcorrencia($id));

        return response()->noContent();
    }

    public function storeAtribuicao(StoreEscalaAtribuicaoRequest $request, string $id): JsonResponse
    {
        $atribuicao = $this->escalas->createAtribuicao($id, $request->validated());

        return (new EscalaAtribuicaoResource($atribuicao))->response()->setStatusCode(201);
    }

    public function destroyAtribuicao(string $id): Response
    {
        abort_unless($this->access->canManage(), 403);
        $this->escalas->deleteAtribuicao($this->escalas->findAtribuicao($id));

        return response()->noContent();
    }

    public function agenda(Request $request): AnonymousResourceCollection
    {
        abort_unless($this->access->canView(), 403);

        return EscalaOcorrenciaResource::collection(
            $this->escalas->agenda(
                $request->query('from'),
                $request->query('to'),
                $request->query('tipo_id'),
            )
        );
    }

    public function candidatos(Request $request): JsonResponse
    {
        abort_unless($this->access->canView(), 403);

        return response()->json([
            'data' => $this->escalas->candidatos($request->query('q')),
        ]);
    }
}
