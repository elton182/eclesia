<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportEccCasaisRequest;
use App\Http\Requests\StoreEccCasalRequest;
use App\Http\Requests\UpdateEccCasalRequest;
use App\Http\Resources\EccCasalResource;
use App\Services\EccCasalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class EccCasalController extends Controller
{
    public function __construct(private readonly EccCasalService $casais) {}

    public function index(): AnonymousResourceCollection
    {
        return EccCasalResource::collection($this->casais->list());
    }

    public function store(StoreEccCasalRequest $request): JsonResponse
    {
        $casal = $this->casais->create($request->validated());

        return (new EccCasalResource($casal))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): EccCasalResource
    {
        return new EccCasalResource($this->casais->find($id));
    }

    public function update(UpdateEccCasalRequest $request, string $id): EccCasalResource
    {
        $casal = $this->casais->find($id);

        return new EccCasalResource($this->casais->update($casal, $request->validated()));
    }

    public function destroy(string $id): Response
    {
        $this->casais->delete($this->casais->find($id));

        return response()->noContent();
    }

    public function import(ImportEccCasaisRequest $request): JsonResponse
    {
        $result = $this->casais->import($request->validated('rows'));

        return response()->json($result);
    }
}
