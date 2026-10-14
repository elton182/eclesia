<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RejeitarEccFinanceiroDepositoRequest;
use App\Http\Requests\StoreEccFinanceiroDepositoRequest;
use App\Http\Requests\UpdateEccFinanceiroDepositoRequest;
use App\Models\User;
use App\Services\EccFinanceiroDepositoService;
use App\Services\EccVisibilityScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EccFinanceiroDepositoController extends Controller
{
    public function __construct(
        private readonly EccFinanceiroDepositoService $depositos,
        private readonly EccVisibilityScope $visibility,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = array_filter([
            'status' => $request->query('status'),
            'competencia' => $request->query('competencia'),
            'equipe_id' => $request->query('equipe_id'),
        ], static fn (mixed $v): bool => $v !== null && $v !== '');

        return response()->json(['data' => $this->depositos->list($filters)]);
    }

    public function store(StoreEccFinanceiroDepositoRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $deposito = $this->depositos->create(
            $request->safe()->except('comprovante'),
            $request->file('comprovante'),
            $user,
        );

        return response()->json(['data' => $this->depositos->serialize($deposito)], 201);
    }

    public function update(UpdateEccFinanceiroDepositoRequest $request, string $id): JsonResponse
    {
        $deposito = $this->depositos->update(
            $this->depositos->find($id),
            $request->safe()->except('comprovante'),
            $request->file('comprovante'),
        );

        return response()->json(['data' => $this->depositos->serialize($deposito)]);
    }

    public function comprovante(string $id): StreamedResponse
    {
        $deposito = $this->depositos->find($id);

        abort_unless(
            $this->visibility->userCan('ecc.financeiro.depositos.submeter')
                || $this->visibility->userCan('ecc.financeiro.view')
                || $this->visibility->userCan('ecc.financeiro.manage')
                || $this->visibility->userCan('telas.financeiro'),
            403,
        );

        $path = $deposito->comprovante_path;
        abort_unless($path !== '' && Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path);
    }

    public function aprovar(Request $request, string $id): JsonResponse
    {
        abort_unless($this->visibility->userCan('ecc.financeiro.manage'), 403);

        /** @var User $user */
        $user = $request->user();
        $deposito = $this->depositos->aprovar($this->depositos->find($id), $user);

        return response()->json(['data' => $this->depositos->serialize($deposito)]);
    }

    public function rejeitar(RejeitarEccFinanceiroDepositoRequest $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $deposito = $this->depositos->rejeitar(
            $this->depositos->find($id),
            (string) $request->validated('motivo'),
            $user,
        );

        return response()->json(['data' => $this->depositos->serialize($deposito)]);
    }
}
