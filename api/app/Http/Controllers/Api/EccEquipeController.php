<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEccEquipeRequest;
use App\Http\Requests\UpdateEccEquipeRequest;
use App\Http\Resources\EccEquipeResource;
use App\Models\EccEquipe;
use App\Services\EccEquipeService;
use App\Services\IgrejaContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class EccEquipeController extends Controller
{
    public function __construct(
        private readonly EccEquipeService $equipes,
        private readonly IgrejaContext $igrejaContext,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return EccEquipeResource::collection($this->equipes->list());
    }

    public function store(StoreEccEquipeRequest $request): JsonResponse
    {
        $equipe = $this->equipes->create($request->validated());

        return (new EccEquipeResource($equipe->loadCount('casais')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): EccEquipeResource
    {
        $equipe = EccEquipe::query()
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->withCount('casais')
            ->findOrFail($id);

        return new EccEquipeResource($equipe);
    }

    public function update(UpdateEccEquipeRequest $request, string $id): EccEquipeResource
    {
        $equipe = EccEquipe::query()
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->findOrFail($id);

        return new EccEquipeResource($this->equipes->update($equipe, $request->validated()));
    }

    public function destroy(string $id): Response
    {
        $equipe = EccEquipe::query()
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->findOrFail($id);

        $this->equipes->delete($equipe);

        return response()->noContent();
    }
}
