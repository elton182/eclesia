<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListClientDiagnosticsRequest;
use App\Http\Requests\StoreClientDiagnosticRequest;
use App\Http\Resources\ClientDiagnosticResource;
use App\Services\ClientDiagnosticService;
use App\Services\IgrejaContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientDiagnosticController extends Controller
{
    public function __construct(
        private readonly ClientDiagnosticService $diagnostics,
        private readonly IgrejaContext $igrejaContext,
    ) {}

    public function store(StoreClientDiagnosticRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $igrejaId = null;
        try {
            $igrejaId = (string) $this->igrejaContext->current()->id;
        } catch (\Throwable) {
            $igrejaId = null;
        }

        $row = $this->diagnostics->store(
            $user,
            $request->validated(),
            $request,
            $igrejaId,
        );

        return (new ClientDiagnosticResource($row))
            ->response()
            ->setStatusCode(201);
    }

    public function index(ListClientDiagnosticsRequest $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can('auditoria.view'), 403);

        $paginator = $this->diagnostics->list($request->validated());

        return ClientDiagnosticResource::collection($paginator);
    }

    public function show(string $id): ClientDiagnosticResource
    {
        abort_unless(request()->user()?->can('auditoria.view'), 403);

        $row = $this->diagnostics->findByUlid($id);
        abort_if($row === null, 404);

        return new ClientDiagnosticResource($row);
    }
}
