<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEccRecadoRequest;
use App\Http\Requests\UpdateEccRecadoRequest;
use App\Models\User;
use App\Services\EccRecadoService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EccRecadoController extends Controller
{
    public function __construct(
        private readonly EccRecadoService $recados,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = array_filter([
            'status' => $request->query('status'),
        ], static fn (mixed $v): bool => $v !== null && $v !== '');

        return response()->json(['data' => $this->recados->list($filters)]);
    }

    public function novidades(Request $request): JsonResponse
    {
        $desdeRaw = $request->query('desde');
        if (! is_string($desdeRaw) || trim($desdeRaw) === '') {
            throw ValidationException::withMessages([
                'desde' => ['Informe a data/hora inicial.'],
            ]);
        }

        try {
            $desde = Carbon::parse($desdeRaw);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'desde' => ['Data/hora inválida.'],
            ]);
        }

        return response()->json([
            'data' => ['count' => $this->recados->novidadesCount($desde)],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(['data' => $this->recados->serialize($this->recados->find($id))]);
    }

    public function store(StoreEccRecadoRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $recado = $this->recados->create(
            $request->safe()->except(['anexo']),
            $user,
            $request->file('anexo'),
        );

        return response()->json(['data' => $this->recados->serialize($recado)], 201);
    }

    public function update(UpdateEccRecadoRequest $request, string $id): JsonResponse
    {
        $removerAnexo = filter_var($request->input('remover_anexo'), FILTER_VALIDATE_BOOLEAN);
        $recado = $this->recados->update(
            $this->recados->find($id),
            $request->safe()->except(['anexo', 'remover_anexo']),
            $request->file('anexo'),
            $removerAnexo,
        );

        return response()->json(['data' => $this->recados->serialize($recado)]);
    }

    public function destroy(string $id): Response
    {
        $this->recados->delete($this->recados->find($id));

        return response()->noContent();
    }

    public function anexo(string $id): StreamedResponse
    {
        return $this->recados->streamAnexo($this->recados->find($id));
    }
}
