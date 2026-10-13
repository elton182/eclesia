<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePastoralDomainRequest;
use App\Http\Requests\StorePastoralMembroRequest;
use App\Http\Requests\UpdatePastoralDomainRequest;
use App\Http\Resources\PastoralMembroResource;
use App\Http\Resources\PastoralResource;
use App\Services\PastoralAccessService;
use App\Services\PastoralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PastoraisController extends Controller
{
    public function __construct(
        private readonly PastoralService $pastorais,
        private readonly PastoralAccessService $access,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        abort_unless($this->access->canView(), 403);

        return PastoralResource::collection($this->pastorais->list());
    }

    public function store(StorePastoralDomainRequest $request): JsonResponse
    {
        abort_unless($this->access->canManage(), 403);

        $item = $this->pastorais->create($request->validated());

        return (new PastoralResource($item))->response()->setStatusCode(201);
    }

    public function show(string $id): PastoralResource
    {
        abort_unless($this->access->canView(), 403);

        return new PastoralResource($this->pastorais->find($id));
    }

    public function update(UpdatePastoralDomainRequest $request, string $id): PastoralResource
    {
        abort_unless($this->access->canManage(), 403);

        return new PastoralResource(
            $this->pastorais->update($this->pastorais->find($id), $request->validated())
        );
    }

    public function destroy(string $id): Response
    {
        abort_unless($this->access->canManage(), 403);
        $this->pastorais->delete($this->pastorais->find($id));

        return response()->noContent();
    }

    public function indexMembros(string $id): AnonymousResourceCollection
    {
        abort_unless($this->access->canView(), 403);

        return PastoralMembroResource::collection(
            $this->pastorais->listMembros($this->pastorais->find($id))
        );
    }

    public function storeMembro(StorePastoralMembroRequest $request, string $id): JsonResponse
    {
        abort_unless($this->access->canManage(), 403);

        $pastoral = $this->pastorais->find($id);
        $this->pastorais->attachMembro($pastoral, $request->validated());

        $membro = $pastoral->membros()
            ->where('ulid', $request->validated('user_id'))
            ->firstOrFail();

        return (new PastoralMembroResource($membro))->response()->setStatusCode(201);
    }

    public function destroyMembro(string $id, string $userId): Response
    {
        abort_unless($this->access->canManage(), 403);
        $this->pastorais->detachMembro($this->pastorais->find($id), $userId);

        return response()->noContent();
    }
}
