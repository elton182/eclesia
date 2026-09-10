<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePastoralRequest;
use App\Http\Requests\UpdatePastoralRequest;
use App\Http\Resources\PastoralResource;
use App\Models\Pastoral;
use App\Services\IgrejaAccessService;
use App\Services\SiteAccessService;
use App\Services\SiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PastoralController extends Controller
{
    public function __construct(
        private readonly SiteService $site,
        private readonly SiteAccessService $access,
        private readonly IgrejaAccessService $igrejas,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        abort_unless($this->access->canViewPastorais(auth()->user()), 403);

        $query = Pastoral::query()->with('igreja')->orderBy('ordem')->orderBy('nome');
        $user = auth()->user();

        if (! $this->access->isOrgSiteManager($user)) {
            $ids = $this->igrejas->accessibleIds($user);
            $query->whereIn('igreja_id', $ids);
        }

        return PastoralResource::collection($query->get());
    }

    public function store(StorePastoralRequest $request): JsonResponse
    {
        $data = $request->validated();
        abort_unless($this->access->canManagePastoral(auth()->user(), $data['igreja_id']), 403);

        $item = $this->site->createPastoral($data);

        return (new PastoralResource($item->load('igreja')))->response()->setStatusCode(201);
    }

    public function show(Pastoral $pastoral): PastoralResource
    {
        abort_unless($this->access->canViewPastorais(auth()->user()), 403);
        if (! $this->access->isOrgSiteManager(auth()->user())) {
            abort_unless($this->igrejas->canAccess(auth()->user(), $pastoral->igreja_id), 403);
        }

        return new PastoralResource($pastoral->load('igreja'));
    }

    public function update(UpdatePastoralRequest $request, Pastoral $pastoral): PastoralResource
    {
        $data = $request->validated();
        $igrejaId = $data['igreja_id'] ?? $pastoral->igreja_id;
        abort_unless($this->access->canManagePastoral(auth()->user(), $igrejaId), 403);

        return new PastoralResource(
            $this->site->updatePastoral($pastoral, $data)->load('igreja')
        );
    }

    public function destroy(Pastoral $pastoral): Response
    {
        abort_unless($this->access->canManagePastoral(auth()->user(), $pastoral->igreja_id), 403);
        $pastoral->delete();

        return response()->noContent();
    }
}
