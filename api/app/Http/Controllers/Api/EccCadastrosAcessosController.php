<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EccCadastrosAcessosRelatorioRequest;
use App\Services\EccCadastrosAcessosService;
use App\Services\EccVisibilityScope;
use Illuminate\Http\JsonResponse;

class EccCadastrosAcessosController extends Controller
{
    public function __construct(
        private readonly EccCadastrosAcessosService $relatorio,
        private readonly EccVisibilityScope $visibility,
    ) {}

    public function show(EccCadastrosAcessosRelatorioRequest $request): JsonResponse
    {
        $this->assertCanView();

        $janela = (int) ($request->validated('janela_dias') ?? 30);

        return response()->json([
            'data' => $this->relatorio->relatorio($janela),
        ]);
    }

    private function assertCanView(): void
    {
        abort_unless(
            $this->visibility->userCan('ecc.relatorios.view')
                || $this->visibility->userCan('telas.relatorios'),
            403,
        );
    }
}
