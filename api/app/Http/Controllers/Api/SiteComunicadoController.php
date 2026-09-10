<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSiteComunicadoRequest;
use App\Http\Requests\UpdateSiteComunicadoRequest;
use App\Http\Resources\SiteComunicadoResource;
use App\Models\SiteComunicado;
use App\Services\IgrejaAccessService;
use App\Services\SiteAccessService;
use App\Services\SiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SiteComunicadoController extends Controller
{
    public function __construct(
        private readonly SiteService $site,
        private readonly SiteAccessService $access,
        private readonly IgrejaAccessService $igrejas,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        abort_unless($this->access->canViewComunicados(auth()->user()), 403);

        $query = SiteComunicado::query()->orderByDesc('created_at');
        $user = auth()->user();

        if (! $this->access->isOrgSiteManager($user)) {
            $ids = $this->igrejas->accessibleIds($user);
            $query->whereIn('igreja_id', $ids);
        }

        return SiteComunicadoResource::collection($query->get());
    }

    public function store(StoreSiteComunicadoRequest $request): JsonResponse
    {
        $data = $request->validated();
        abort_unless($this->access->canManageComunicado(auth()->user(), $data['igreja_id'] ?? null), 403);

        $item = $this->site->createComunicado($data);

        return (new SiteComunicadoResource($item))->response()->setStatusCode(201);
    }

    public function show(SiteComunicado $comunicado): SiteComunicadoResource
    {
        abort_unless($this->access->canViewComunicados(auth()->user()), 403);
        if (! $this->access->isOrgSiteManager(auth()->user())) {
            abort_unless(
                $comunicado->igreja_id !== null
                && $this->igrejas->canAccess(auth()->user(), $comunicado->igreja_id),
                403
            );
        }

        return new SiteComunicadoResource($comunicado);
    }

    public function update(UpdateSiteComunicadoRequest $request, SiteComunicado $comunicado): SiteComunicadoResource
    {
        $data = $request->validated();
        $igrejaId = $data['igreja_id'] ?? $comunicado->igreja_id;
        abort_unless($this->access->canManageComunicado(auth()->user(), $igrejaId), 403);

        return new SiteComunicadoResource(
            $this->site->updateComunicado($comunicado, $data)
        );
    }

    public function destroy(SiteComunicado $comunicado): Response
    {
        abort_unless($this->access->canManageComunicado(auth()->user(), $comunicado->igreja_id), 403);
        $comunicado->delete();

        return response()->noContent();
    }
}
