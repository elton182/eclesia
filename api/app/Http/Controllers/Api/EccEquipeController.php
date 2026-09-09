<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEccEquipeRequest;
use App\Http\Requests\UpdateEccEquipeRequest;
use App\Http\Resources\EccEquipeResource;
use App\Services\EccEquipeService;
use App\Services\EccVisibilityScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class EccEquipeController extends Controller
{
    public function __construct(
        private readonly EccEquipeService $equipes,
        private readonly EccVisibilityScope $visibility,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        abort_unless($this->visibility->userCan('ecc.equipes.view') || $this->visibility->userCan('ecc.equipes.manage') || $this->visibility->userCan('ecc.casais.manage'), 403);

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
        abort_unless($this->visibility->userCan('ecc.equipes.view') || $this->visibility->userCan('ecc.equipes.manage') || $this->visibility->userCan('ecc.casais.manage'), 403);

        return new EccEquipeResource($this->equipes->find($id));
    }

    public function update(UpdateEccEquipeRequest $request, string $id): EccEquipeResource
    {
        $equipe = $this->equipes->find($id);

        return new EccEquipeResource($this->equipes->update($equipe, $request->validated()));
    }

    public function destroy(string $id): Response
    {
        abort_unless($this->visibility->userCan('ecc.equipes.manage'), 403);

        $this->equipes->delete($this->equipes->find($id));

        return response()->noContent();
    }
}
