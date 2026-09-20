<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EccEquipeServico;
use App\Models\Igreja;
use Illuminate\Database\Eloquent\Collection;

class EccEquipeServicoService
{
    public function __construct(
        private readonly IgrejaContext $igrejaContext,
    ) {}

    /**
     * @return Collection<int, EccEquipeServico>
     */
    public function listAtivas(): Collection
    {
        $igreja = $this->igrejaContext->current();
        EccEquipeServico::seedDefaultsForIgreja($igreja->id);

        return EccEquipeServico::query()
            ->where('igreja_id', $igreja->id)
            ->where('ativo', true)
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get();
    }

    public function seedForIgreja(Igreja|string $igreja): void
    {
        $id = $igreja instanceof Igreja ? $igreja->id : $igreja;
        EccEquipeServico::seedDefaultsForIgreja($id);
    }
}
