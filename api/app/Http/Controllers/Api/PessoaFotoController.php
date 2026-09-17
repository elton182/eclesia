<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePessoaFotoRequest;
use App\Http\Resources\PessoaResource;
use App\Models\Pessoa;
use App\Services\EccVisibilityScope;
use App\Services\PessoaFotoService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PessoaFotoController extends Controller
{
    public function __construct(
        private readonly PessoaFotoService $fotos,
        private readonly EccVisibilityScope $visibility,
    ) {}

    /**
     * Serve a foto do storage do tenant via URL assinada (sem Bearer / X-Tenant header).
     */
    public function show(string $id): StreamedResponse|Response
    {
        $pessoa = Pessoa::query()->findOrFail($id);
        $path = $pessoa->foto_path;

        if ($path === null || $path === '' || ! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return Storage::disk('public')->response($path);
    }

    public function store(StorePessoaFotoRequest $request, string $id): PessoaResource
    {
        $pessoa = $this->fotos->findInCurrentIgreja($id);
        $pessoa = $this->fotos->store($pessoa, $request->file('file'));

        return new PessoaResource($pessoa);
    }

    public function destroy(string $id): Response
    {
        abort_unless(
            $this->visibility->userCan('pessoas.manage') || $this->visibility->userCan('ecc.casais.manage'),
            403
        );

        $pessoa = $this->fotos->findInCurrentIgreja($id);
        $this->fotos->destroy($pessoa);

        return response()->noContent();
    }
}
