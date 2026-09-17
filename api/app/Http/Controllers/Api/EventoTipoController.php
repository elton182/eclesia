<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventoTipoRequest;
use App\Http\Requests\UpdateEventoTipoRequest;
use App\Http\Resources\EventoTipoResource;
use App\Services\EccVisibilityScope;
use App\Services\EventoTipoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class EventoTipoController extends Controller
{
    public function __construct(
        private readonly EventoTipoService $tipos,
        private readonly EccVisibilityScope $visibility,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless(
            $this->visibility->userCan('ecc.eventos.view') || $this->visibility->userCan('ecc.eventos.manage'),
            403,
        );

        return EventoTipoResource::collection($this->tipos->list([
            'escopo' => $request->query('escopo'),
        ]));
    }

    public function store(StoreEventoTipoRequest $request): JsonResponse
    {
        $tipo = $this->tipos->create($request->validated());

        return (new EventoTipoResource($tipo))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateEventoTipoRequest $request, string $id): EventoTipoResource
    {
        $tipo = $this->tipos->update($this->tipos->find($id), $request->validated());

        return new EventoTipoResource($tipo);
    }

    public function destroy(string $id): Response
    {
        abort_unless($this->visibility->userCan('ecc.eventos.manage'), 403);

        $this->tipos->delete($this->tipos->find($id));

        return response()->noContent();
    }
}
