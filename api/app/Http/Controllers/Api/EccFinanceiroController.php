<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEccFinanceiroContaRequest;
use App\Http\Requests\StoreEccFinanceiroLancamentoRequest;
use App\Http\Requests\StoreEccFinanceiroTransferenciaRequest;
use App\Http\Requests\TransportarEccFinanceiroRequest;
use App\Http\Requests\UpdateEccFinanceiroContaRequest;
use App\Http\Requests\UpdateEccFinanceiroLancamentoRequest;
use App\Services\EccFinanceiroService;
use App\Services\EccVisibilityScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class EccFinanceiroController extends Controller
{
    public function __construct(
        private readonly EccFinanceiroService $financeiro,
        private readonly EccVisibilityScope $visibility,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->assertCanView();

        $ano = (int) $request->query('ano', 0);
        if ($ano < 2000 || $ano > 2100) {
            throw ValidationException::withMessages([
                'ano' => ['Informe um ano válido (2000–2100).'],
            ]);
        }

        return response()->json(['data' => $this->financeiro->livro($ano)]);
    }

    public function listContas(): JsonResponse
    {
        $this->assertCanView();

        return response()->json([
            'data' => $this->financeiro->listContas()->map(static fn ($c) => [
                'id' => $c->id,
                'nome' => $c->nome,
                'tipo' => $c->tipo,
                'ordem' => $c->ordem,
                'ativa' => $c->ativa,
            ])->values()->all(),
        ]);
    }

    public function storeConta(StoreEccFinanceiroContaRequest $request): JsonResponse
    {
        $conta = $this->financeiro->createConta($request->validated());

        return response()->json([
            'data' => [
                'id' => $conta->id,
                'nome' => $conta->nome,
                'tipo' => $conta->tipo,
                'ordem' => $conta->ordem,
                'ativa' => $conta->ativa,
            ],
        ], 201);
    }

    public function updateConta(UpdateEccFinanceiroContaRequest $request, string $id): JsonResponse
    {
        $conta = $this->financeiro->updateConta($this->financeiro->findConta($id), $request->validated());

        return response()->json([
            'data' => [
                'id' => $conta->id,
                'nome' => $conta->nome,
                'tipo' => $conta->tipo,
                'ordem' => $conta->ordem,
                'ativa' => $conta->ativa,
            ],
        ]);
    }

    public function destroyConta(string $id): Response
    {
        abort_unless($this->visibility->userCan('ecc.financeiro.manage'), 403);

        $this->financeiro->deleteConta($this->financeiro->findConta($id));

        return response()->noContent();
    }

    public function storeLancamento(StoreEccFinanceiroLancamentoRequest $request): JsonResponse
    {
        $lancamento = $this->financeiro->createLancamento($request->validated());

        return response()->json([
            'data' => $this->financeiro->serializeLancamento($lancamento),
        ], 201);
    }

    public function updateLancamento(UpdateEccFinanceiroLancamentoRequest $request, string $id): JsonResponse
    {
        $atualizados = $this->financeiro->updateLancamento(
            $this->financeiro->findLancamento($id),
            $request->validated()
        );

        return response()->json([
            'data' => array_map(
                fn ($l) => $this->financeiro->serializeLancamento($l),
                $atualizados
            ),
        ]);
    }

    public function destroyLancamento(string $id): Response
    {
        abort_unless($this->visibility->userCan('ecc.financeiro.manage'), 403);

        $this->financeiro->deleteLancamento($this->financeiro->findLancamento($id));

        return response()->noContent();
    }

    public function transferir(StoreEccFinanceiroTransferenciaRequest $request): JsonResponse
    {
        $par = $this->financeiro->transferir($request->validated());

        return response()->json([
            'data' => [
                'saida' => $this->financeiro->serializeLancamento($par['saida']),
                'entrada' => $this->financeiro->serializeLancamento($par['entrada']),
            ],
        ], 201);
    }

    public function transportar(TransportarEccFinanceiroRequest $request): JsonResponse
    {
        $criados = $this->financeiro->transportar((int) $request->validated('ano'));

        return response()->json([
            'data' => array_map(
                fn ($l) => $this->financeiro->serializeLancamento($l),
                $criados
            ),
        ], 201);
    }

    private function assertCanView(): void
    {
        abort_unless(
            $this->visibility->userCan('ecc.financeiro.view')
                || $this->visibility->userCan('ecc.financeiro.manage')
                || $this->visibility->userCan('telas.financeiro'),
            403,
        );
    }
}
