<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EccLeitura;
use App\Models\User;
use App\Services\EccLeituraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EccMuralController extends Controller
{
    public function __construct(
        private readonly EccLeituraService $leituras,
    ) {}

    public function naoLidos(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->leituras->naoLidos($user instanceof User ? $user : null),
        ]);
    }

    public function lerRecado(Request $request, string $id): JsonResponse
    {
        return $this->ler($request, EccLeitura::ALVO_RECADO, $id);
    }

    public function lerReflexao(Request $request, string $id): JsonResponse
    {
        return $this->ler($request, EccLeitura::ALVO_REFLEXAO, $id);
    }

    public function engajamentoRecado(string $id): JsonResponse
    {
        return response()->json([
            'data' => $this->leituras->engajamento(EccLeitura::ALVO_RECADO, $id),
        ]);
    }

    public function engajamentoReflexao(string $id): JsonResponse
    {
        return response()->json([
            'data' => $this->leituras->engajamento(EccLeitura::ALVO_REFLEXAO, $id),
        ]);
    }

    private function ler(Request $request, string $alvoTipo, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $leitura = $this->leituras->marcarLido($alvoTipo, $id, $user);

        return response()->json([
            'data' => [
                'lido_por_mim' => true,
                'lido_em' => $leitura->lido_em?->toIso8601String(),
            ],
        ]);
    }
}
