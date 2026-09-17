<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Casal;
use App\Models\Pessoa;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PessoaFotoService
{
    public function __construct(
        private readonly IgrejaContext $igrejaContext,
    ) {}

    public function findInCurrentIgreja(string $id): Pessoa
    {
        return Pessoa::query()
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->findOrFail($id);
    }

    public function store(Pessoa $pessoa, UploadedFile $file): Pessoa
    {
        $this->deleteFile($pessoa);

        $path = $file->store('pessoas/fotos', 'public');

        $pessoa->update(['foto_path' => $path]);
        $this->syncFichaComFoto($pessoa);

        return $pessoa->refresh();
    }

    public function destroy(Pessoa $pessoa): void
    {
        $this->deleteFile($pessoa);
        $pessoa->update(['foto_path' => null]);
        $this->syncFichaComFoto($pessoa);
    }

    public function deleteFile(Pessoa $pessoa): void
    {
        $path = $pessoa->foto_path;
        if ($path === null || $path === '') {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    public function syncFichaComFoto(Pessoa $pessoa): void
    {
        $casais = Casal::query()
            ->where(function ($query) use ($pessoa): void {
                $query->where('pessoa_a_id', $pessoa->id)
                    ->orWhere('pessoa_b_id', $pessoa->id);
            })
            ->with(['pessoaA', 'pessoaB'])
            ->get();

        foreach ($casais as $casal) {
            $hasFoto = filled($casal->pessoaA?->foto_path) || filled($casal->pessoaB?->foto_path);
            if ((bool) $casal->ficha_com_foto !== $hasFoto) {
                $casal->update(['ficha_com_foto' => $hasFoto]);
            }
        }
    }
}
