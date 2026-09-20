<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCalendarioColetaLinkRequest;
use App\Http\Requests\StoreCalendarioEventoTipoRequest;
use App\Http\Requests\StoreCalendarioIndisponibilidadeRequest;
use App\Http\Requests\StoreCalendarioItemRequest;
use App\Http\Requests\StoreCalendarioLocalRequest;
use App\Http\Requests\StoreCalendarioMensalRequest;
use App\Http\Requests\StoreCalendarioObservacaoRequest;
use App\Http\Requests\StoreCalendarioSlotRequest;
use App\Http\Requests\TransitionCalendarioStatusRequest;
use App\Http\Requests\UpdateCalendarioEventoTipoRequest;
use App\Http\Requests\UpdateCalendarioItemRequest;
use App\Http\Requests\UpdateCalendarioLocalRequest;
use App\Http\Requests\UpdateCalendarioMensalRequest;
use App\Http\Requests\UpdateCalendarioObservacaoRequest;
use App\Http\Requests\UpdateCalendarioSlotRequest;
use App\Http\Requests\UpdateCalendarioTempoLiturgicoRequest;
use App\Http\Resources\CalendarioColetaLinkResource;
use App\Http\Resources\CalendarioEventoTipoResource;
use App\Http\Resources\CalendarioIndisponibilidadeResource;
use App\Http\Resources\CalendarioItemResource;
use App\Http\Resources\CalendarioLocalResource;
use App\Http\Resources\CalendarioMensalResource;
use App\Http\Resources\CalendarioObservacaoResource;
use App\Http\Resources\CalendarioSlotPadraoResource;
use App\Http\Resources\CalendarioTempoLiturgicoResource;
use App\Models\User;
use App\Services\CalendarioAccessService;
use App\Services\CalendarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CalendarioController extends Controller
{
    public function __construct(
        private readonly CalendarioService $calendario,
        private readonly CalendarioAccessService $access,
    ) {}

    public function indexLocais(): AnonymousResourceCollection
    {
        abort_unless($this->access->canView(), 403);

        return CalendarioLocalResource::collection($this->calendario->listLocais());
    }

    public function indexEventoTipos(Request $request): AnonymousResourceCollection
    {
        abort_unless($this->access->canView(), 403);
        $todos = $this->access->canManage() && $request->boolean('todos');

        return CalendarioEventoTipoResource::collection($this->calendario->listEventoTipos(! $todos));
    }

    public function storeEventoTipo(StoreCalendarioEventoTipoRequest $request): JsonResponse
    {
        abort_unless($this->access->canManage(), 403);
        $tipo = $this->calendario->createEventoTipo($request->validated());

        return (new CalendarioEventoTipoResource($tipo))->response()->setStatusCode(201);
    }

    public function updateEventoTipo(UpdateCalendarioEventoTipoRequest $request, string $id): CalendarioEventoTipoResource
    {
        abort_unless($this->access->canManage(), 403);

        return new CalendarioEventoTipoResource(
            $this->calendario->updateEventoTipo($this->calendario->findEventoTipo($id), $request->validated())
        );
    }

    public function destroyEventoTipo(string $id): Response
    {
        abort_unless($this->access->canManage(), 403);
        $this->calendario->deleteEventoTipo($this->calendario->findEventoTipo($id));

        return response()->noContent();
    }

    public function storeLocal(StoreCalendarioLocalRequest $request): JsonResponse
    {
        abort_unless($this->access->canManage(), 403);
        $local = $this->calendario->createLocal($request->validated());

        return (new CalendarioLocalResource($local))->response()->setStatusCode(201);
    }

    public function updateLocal(UpdateCalendarioLocalRequest $request, string $id): CalendarioLocalResource
    {
        abort_unless($this->access->canManage(), 403);

        return new CalendarioLocalResource(
            $this->calendario->updateLocal($this->calendario->findLocal($id), $request->validated())
        );
    }

    public function destroyLocal(string $id): Response
    {
        abort_unless($this->access->canManage(), 403);
        $this->calendario->deleteLocal($this->calendario->findLocal($id));

        return response()->noContent();
    }

    public function indexSlots(): AnonymousResourceCollection
    {
        abort_unless($this->access->canView(), 403);

        return CalendarioSlotPadraoResource::collection($this->calendario->listSlots());
    }

    public function storeSlot(StoreCalendarioSlotRequest $request): JsonResponse
    {
        abort_unless($this->access->canManage(), 403);
        $slot = $this->calendario->createSlot($request->validated());

        return (new CalendarioSlotPadraoResource($slot))->response()->setStatusCode(201);
    }

    public function updateSlot(UpdateCalendarioSlotRequest $request, string $id): CalendarioSlotPadraoResource
    {
        abort_unless($this->access->canManage(), 403);

        return new CalendarioSlotPadraoResource(
            $this->calendario->updateSlot($this->calendario->findSlot($id), $request->validated())
        );
    }

    public function destroySlot(string $id): Response
    {
        abort_unless($this->access->canManage(), 403);
        $this->calendario->deleteSlot($this->calendario->findSlot($id));

        return response()->noContent();
    }

    public function indexMensais(): AnonymousResourceCollection
    {
        abort_unless($this->access->canView(), 403);

        return CalendarioMensalResource::collection($this->calendario->listMensais());
    }

    public function storeMensal(StoreCalendarioMensalRequest $request): JsonResponse
    {
        abort_unless($this->access->canManage(), 403);
        $mensal = $this->calendario->createMensal($request->validated());

        return (new CalendarioMensalResource($mensal))->response()->setStatusCode(201);
    }

    public function showMensal(string $id): CalendarioMensalResource
    {
        abort_unless($this->access->canView(), 403);

        return new CalendarioMensalResource($this->calendario->findMensal($id));
    }

    public function updateMensal(UpdateCalendarioMensalRequest $request, string $id): CalendarioMensalResource
    {
        abort_unless($this->access->canManage(), 403);

        return new CalendarioMensalResource(
            $this->calendario->updateMensal($this->calendario->findMensal($id), $request->validated())
        );
    }

    public function destroyMensal(string $id): Response
    {
        abort_unless($this->access->canManage(), 403);
        $this->calendario->deleteMensal($this->calendario->findMensal($id));

        return response()->noContent();
    }

    public function copiarProximo(string $id): JsonResponse
    {
        abort_unless($this->access->canManage(), 403);
        $novo = $this->calendario->copiarParaProximoMes($this->calendario->findMensal($id));

        return (new CalendarioMensalResource($novo))->response()->setStatusCode(201);
    }

    public function transitionStatus(TransitionCalendarioStatusRequest $request, string $id): CalendarioMensalResource
    {
        abort_unless($this->access->canManage(), 403);

        return new CalendarioMensalResource(
            $this->calendario->transitionStatus($this->calendario->findMensal($id), $request->validated()['status'])
        );
    }

    public function storeItem(StoreCalendarioItemRequest $request, string $id): JsonResponse
    {
        abort_unless($this->access->canManage(), 403);
        $item = $this->calendario->createItem($this->calendario->findMensal($id), $request->validated());

        return (new CalendarioItemResource($item))->response()->setStatusCode(201);
    }

    public function updateItem(UpdateCalendarioItemRequest $request, string $itemId): CalendarioItemResource
    {
        abort_unless($this->access->canManage(), 403);

        return new CalendarioItemResource(
            $this->calendario->updateItem($this->calendario->findItem($itemId), $request->validated())
        );
    }

    public function destroyItem(string $itemId): Response
    {
        abort_unless($this->access->canManage(), 403);
        $this->calendario->deleteItem($this->calendario->findItem($itemId));

        return response()->noContent();
    }

    public function storeObservacao(StoreCalendarioObservacaoRequest $request, string $id): JsonResponse
    {
        abort_unless($this->access->canManage(), 403);
        $obs = $this->calendario->createObservacao($this->calendario->findMensal($id), $request->validated());

        return (new CalendarioObservacaoResource($obs))->response()->setStatusCode(201);
    }

    public function updateObservacao(UpdateCalendarioObservacaoRequest $request, string $id): CalendarioObservacaoResource
    {
        abort_unless($this->access->canManage(), 403);

        return new CalendarioObservacaoResource(
            $this->calendario->updateObservacao($this->calendario->findObservacao($id), $request->validated())
        );
    }

    public function destroyObservacao(string $id): Response
    {
        abort_unless($this->access->canManage(), 403);
        $this->calendario->deleteObservacao($this->calendario->findObservacao($id));

        return response()->noContent();
    }

    public function updateTempoLiturgico(UpdateCalendarioTempoLiturgicoRequest $request, string $id): CalendarioTempoLiturgicoResource
    {
        abort_unless($this->access->canManage(), 403);

        return new CalendarioTempoLiturgicoResource(
            $this->calendario->updateTempoLiturgico($this->calendario->findTempoLiturgico($id), $request->validated())
        );
    }

    public function storeColetaLink(StoreCalendarioColetaLinkRequest $request, string $id): JsonResponse
    {
        abort_unless($this->access->canManage(), 403);
        $result = $this->calendario->createColetaLink($this->calendario->findMensal($id), $request->validated());

        return response()->json([
            'data' => (new CalendarioColetaLinkResource($result['link']))->resolve(),
            'token' => $result['token'],
        ], 201);
    }

    public function storeIndisponibilidades(StoreCalendarioIndisponibilidadeRequest $request, string $id): JsonResponse
    {
        abort_unless($this->access->canView(), 403);
        $user = auth()->user();
        $userId = $user instanceof User ? $user->id : null;
        $items = $this->calendario->registrarIndisponibilidadesAuth(
            $this->calendario->findMensal($id),
            $request->validated(),
            $userId
        );

        return CalendarioIndisponibilidadeResource::collection($items)->response()->setStatusCode(201);
    }

    public function pdf(string $id): SymfonyResponse
    {
        abort_unless($this->access->canView(), 403);

        return $this->calendario->pdf($this->calendario->findMensal($id));
    }

    public function showColetaPublica(string $token): JsonResponse
    {
        $link = $this->calendario->findColetaLinkByToken($token);
        $mensal = $link->calendario;

        return response()->json([
            'data' => [
                'ano' => $mensal->ano,
                'mes' => $mensal->mes,
                'titulo' => $mensal->titulo,
                'status' => $mensal->status,
                'rotulo' => $link->rotulo,
            ],
        ]);
    }

    public function storeColetaPublica(StoreCalendarioIndisponibilidadeRequest $request, string $token): JsonResponse
    {
        $link = $this->calendario->findColetaLinkByToken($token);
        $items = $this->calendario->registrarIndisponibilidadesPublicas($link, $request->validated());

        return CalendarioIndisponibilidadeResource::collection($items)->response()->setStatusCode(201);
    }
}
