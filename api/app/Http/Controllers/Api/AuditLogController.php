<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListAuditLogsRequest;
use App\Http\Resources\AuditLogResource;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    public function index(ListAuditLogsRequest $request): AnonymousResourceCollection|JsonResponse
    {
        abort_unless($request->user()?->can('auditoria.view'), 403);

        $paginator = $this->auditLogService->list($request->validated());

        return AuditLogResource::collection($paginator);
    }
}
