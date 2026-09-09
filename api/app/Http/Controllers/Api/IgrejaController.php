<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIgrejaRequest;
use App\Http\Requests\UpdateIgrejaRequest;
use App\Http\Resources\IgrejaResource;
use App\Models\Igreja;
use App\Services\IgrejaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class IgrejaController extends Controller
{
    public function __construct(
        private readonly IgrejaService $igrejas,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Igreja::class);

        return IgrejaResource::collection(
            $this->igrejas->listAccessible(auth()->user())
        );
    }

    public function store(StoreIgrejaRequest $request): JsonResponse
    {
        $igreja = $this->igrejas->create($request->validated());

        return (new IgrejaResource($igreja))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Igreja $igreja): IgrejaResource
    {
        $this->authorize('view', $igreja);

        return new IgrejaResource($igreja);
    }

    public function update(UpdateIgrejaRequest $request, Igreja $igreja): IgrejaResource
    {
        return new IgrejaResource(
            $this->igrejas->update($igreja, $request->validated())
        );
    }

    public function destroy(Igreja $igreja): Response
    {
        $this->authorize('delete', $igreja);

        $this->igrejas->delete($igreja);

        return response()->noContent();
    }
}
