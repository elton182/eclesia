<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEccReflexaoRequest;
use App\Http\Requests\UpdateEccReflexaoRequest;
use App\Models\EccReflexao;
use App\Models\User;
use App\Services\EccReflexaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EccReflexaoController extends Controller
{
    public function __construct(
        private readonly EccReflexaoService $reflexoes,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = array_filter([
            'status' => $request->query('status'),
        ], static fn (mixed $v): bool => $v !== null && $v !== '');

        return response()->json(['data' => $this->reflexoes->list($filters)]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(['data' => $this->reflexoes->serialize($this->reflexoes->find($id))]);
    }

    public function store(StoreEccReflexaoRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $reflexao = $this->reflexoes->create(
            $request->safe()->except(['imagem', 'anexo']),
            $request->file('imagem'),
            $user,
            $request->file('anexo'),
        );

        return response()->json(['data' => $this->reflexoes->serialize($reflexao)], 201);
    }

    public function update(UpdateEccReflexaoRequest $request, string $id): JsonResponse
    {
        $removerImagem = filter_var($request->input('remover_imagem'), FILTER_VALIDATE_BOOLEAN);
        $removerAnexo = filter_var($request->input('remover_anexo'), FILTER_VALIDATE_BOOLEAN);
        $reflexao = $this->reflexoes->update(
            $this->reflexoes->find($id),
            $request->safe()->except(['imagem', 'remover_imagem', 'anexo', 'remover_anexo']),
            $request->file('imagem'),
            $removerImagem,
            $request->file('anexo'),
            $removerAnexo,
        );

        return response()->json(['data' => $this->reflexoes->serialize($reflexao)]);
    }

    public function destroy(string $id): Response
    {
        $this->reflexoes->delete($this->reflexoes->find($id));

        return response()->noContent();
    }

    public function imagem(string $id): StreamedResponse
    {
        $reflexao = $this->reflexoes->find($id);
        $path = $reflexao->imagem_path;
        abort_unless(is_string($path) && $path !== '' && Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path);
    }

    public function anexo(string $id): StreamedResponse
    {
        return $this->reflexoes->streamAnexo($this->reflexoes->find($id));
    }

    /**
     * Serve imagem via URL assinada (img não envia Bearer / X-Tenant).
     */
    public function imagemAssinada(string $id): StreamedResponse|Response
    {
        $reflexao = EccReflexao::query()->findOrFail($id);
        $path = $reflexao->imagem_path;

        if ($path === null || $path === '' || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return Storage::disk('public')->response($path);
    }
}
