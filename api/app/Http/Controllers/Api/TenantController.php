<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TenantController extends Controller
{
    public function __construct(private readonly TenantService $tenants) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        return TenantResource::collection($this->tenants->paginate($perPage));
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        $tenant = $this->tenants->create($request->validated());

        return (new TenantResource($tenant))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Tenant $tenant): TenantResource
    {
        return new TenantResource($tenant->loadMissing('aliases'));
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): TenantResource
    {
        return new TenantResource($this->tenants->update($tenant, $request->validated()));
    }

    public function destroy(Tenant $tenant): Response
    {
        $this->tenants->delete($tenant);

        return response()->noContent();
    }
}
