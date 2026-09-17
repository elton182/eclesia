<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ComprarEccItemCompraRequest;
use App\Http\Requests\DoarEccItemCompraRequest;
use App\Http\Requests\StoreEccEventoDoacaoRequest;
use App\Http\Requests\StoreEccEventoParticipanteRequest;
use App\Http\Requests\StoreEccEventoRequest;
use App\Http\Requests\StoreEccItemCompraRequest;
use App\Http\Requests\UpdateEccEventoRequest;
use App\Http\Resources\EccEventoResource;
use App\Http\Resources\EccItemCompraResource;
use App\Models\EccEvento;
use App\Services\EccEventoService;
use App\Services\EccVisibilityScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class EccEventoController extends Controller
{
    public function __construct(
        private readonly EccEventoService $eventos,
        private readonly EccVisibilityScope $visibility,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->assertCanView();

        return EccEventoResource::collection($this->eventos->list([
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'tipo' => $request->query('tipo'),
            'tipo_id' => $request->query('tipo_id'),
            'origem' => $this->origemFromRequest($request),
        ]));
    }

    public function store(StoreEccEventoRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['origem'] = $data['origem'] ?? $this->origemFromRequest($request);

        $evento = $this->eventos->create($data);

        return (new EccEventoResource($evento))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): EccEventoResource
    {
        $this->assertCanView();

        return new EccEventoResource($this->eventos->find($id));
    }

    public function update(UpdateEccEventoRequest $request, string $id): EccEventoResource
    {
        $evento = $this->eventos->find($id);

        return new EccEventoResource($this->eventos->update($evento, $request->validated()));
    }

    public function destroy(string $id): Response
    {
        abort_unless($this->visibility->userCan('ecc.eventos.manage'), 403);

        $this->eventos->delete($this->eventos->find($id));

        return response()->noContent();
    }

    public function addParticipante(StoreEccEventoParticipanteRequest $request, string $id): JsonResponse
    {
        $data = $request->validated();
        $evento = $this->eventos->addParticipante(
            $this->eventos->find($id),
            $data['casal_id'],
            (int) ($data['convidados'] ?? 0),
        );

        return (new EccEventoResource($evento))
            ->response()
            ->setStatusCode(201);
    }

    public function updateParticipante(StoreEccEventoParticipanteRequest $request, string $id): EccEventoResource
    {
        $data = $request->validated();
        $evento = $this->eventos->updateParticipante(
            $this->eventos->find($id),
            $data['casal_id'],
            (int) ($data['convidados'] ?? 0),
        );

        return new EccEventoResource($evento);
    }

    public function removeParticipante(StoreEccEventoParticipanteRequest $request, string $id): Response
    {
        $this->eventos->removeParticipante(
            $this->eventos->find($id),
            $request->validated('casal_id'),
        );

        return response()->noContent();
    }

    public function listItens(string $id): AnonymousResourceCollection
    {
        $this->assertCanView();
        $evento = $this->eventos->find($id);

        return EccItemCompraResource::collection($this->eventos->listItens($evento));
    }

    public function storeItem(StoreEccItemCompraRequest $request, string $id): JsonResponse
    {
        $item = $this->eventos->addItem($this->eventos->find($id), $request->validated());

        return (new EccItemCompraResource($item))
            ->response()
            ->setStatusCode(201);
    }

    public function doarItem(DoarEccItemCompraRequest $request, string $id, string $itemId): EccItemCompraResource
    {
        $evento = $this->eventos->find($id);
        $item = $this->eventos->findItem($evento, $itemId);

        return new EccItemCompraResource(
            $this->eventos->doarItem($evento, $item, $request->validated('casal_id')),
        );
    }

    public function comprarItem(ComprarEccItemCompraRequest $request, string $id, string $itemId): EccItemCompraResource
    {
        $evento = $this->eventos->find($id);
        $item = $this->eventos->findItem($evento, $itemId);

        return new EccItemCompraResource(
            $this->eventos->comprarItem($evento, $item, $request->validated('valor_gasto')),
        );
    }

    public function desfazerItem(string $id, string $itemId): EccItemCompraResource
    {
        abort_unless($this->visibility->userCan('ecc.eventos.manage'), 403);

        $evento = $this->eventos->find($id);
        $item = $this->eventos->findItem($evento, $itemId);

        return new EccItemCompraResource($this->eventos->desfazerItem($evento, $item));
    }

    public function destroyItem(string $id, string $itemId): Response
    {
        abort_unless($this->visibility->userCan('ecc.eventos.manage'), 403);

        $evento = $this->eventos->find($id);
        $item = $this->eventos->findItem($evento, $itemId);
        $this->eventos->deleteItem($evento, $item);

        return response()->noContent();
    }

    public function caixa(string $id): JsonResponse
    {
        $this->assertCanView();

        return response()->json([
            'data' => $this->eventos->relatorioCaixa($this->eventos->find($id)),
        ]);
    }

    public function doarDinheiro(StoreEccEventoDoacaoRequest $request, string $id): JsonResponse
    {
        $lancamento = $this->eventos->doarDinheiro(
            $this->eventos->find($id),
            $request->validated(),
        );

        return response()->json([
            'data' => [
                'id' => $lancamento->id,
                'tipo' => $lancamento->tipo,
                'valor' => (float) $lancamento->valor,
                'descricao' => $lancamento->descricao,
                'casal_id' => $lancamento->casal_id,
                'ecc_equipe_id' => $lancamento->ecc_equipe_id,
                'doador_nome' => $lancamento->doador_nome,
            ],
        ], 201);
    }

    private function origemFromRequest(Request $request): string
    {
        if (str_contains($request->path(), 'ecc/eventos')) {
            return EccEvento::ORIGEM_ECC;
        }

        return EccEvento::ORIGEM_GERAL;
    }

    private function assertCanView(): void
    {
        abort_unless(
            $this->visibility->userCan('ecc.eventos.view') || $this->visibility->userCan('ecc.eventos.manage'),
            403,
        );
    }
}
