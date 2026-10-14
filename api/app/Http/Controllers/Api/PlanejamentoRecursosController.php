<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlanejamentoRecursoRequest;
use App\Http\Requests\UpdatePlanejamentoRecursoRequest;
use App\Http\Resources\PlanejamentoRecursoResource;
use App\Models\PlanejamentoRecurso;
use App\Services\PlanejamentoAccessService;
use App\Services\PlanejamentoRecursoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PlanejamentoRecursosController extends Controller
{
    public function __construct(
        private readonly PlanejamentoRecursoService $recursos,
        private readonly PlanejamentoAccessService $access,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($this->access->canView(), 403);
        $tipo = $request->query('tipo');
        $tipo = is_string($tipo) && in_array($tipo, PlanejamentoRecurso::TIPOS, true) ? $tipo : null;

        return PlanejamentoRecursoResource::collection(
            $this->recursos->list(! $this->access->canGerir(), $tipo)
        );
    }

    public function store(StorePlanejamentoRecursoRequest $request): JsonResponse
    {
        abort_unless($this->access->canGerir(), 403);

        return (new PlanejamentoRecursoResource($this->recursos->create($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdatePlanejamentoRecursoRequest $request, string $id): PlanejamentoRecursoResource
    {
        abort_unless($this->access->canGerir(), 403);

        return new PlanejamentoRecursoResource(
            $this->recursos->update($this->recursos->find($id), $request->validated())
        );
    }

    public function destroy(string $id): JsonResponse|Response
    {
        abort_unless($this->access->canGerir(), 403);
        $inativado = $this->recursos->delete($this->recursos->find($id));

        return $inativado === null
            ? response()->noContent()
            : (new PlanejamentoRecursoResource($inativado))->response();
    }
}
