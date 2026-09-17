<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePessoaFotoRequest;
use App\Http\Resources\PessoaResource;
use App\Services\EccVisibilityScope;
use App\Services\PessoaFotoService;
use Illuminate\Http\Response;

class PessoaFotoController extends Controller
{
    public function __construct(
        private readonly PessoaFotoService $fotos,
        private readonly EccVisibilityScope $visibility,
    ) {}

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
