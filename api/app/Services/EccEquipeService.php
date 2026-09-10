<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EccEquipe;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class EccEquipeService
{
    public function __construct(
        private readonly IgrejaContext $igrejaContext,
        private readonly EccVisibilityScope $visibility,
    ) {}

    /**
     * @return Collection<int, EccEquipe>
     */
    public function list(): Collection
    {
        $query = EccEquipe::query()
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->withCount('casais');

        $equipeIds = $this->visibility->restrictedEquipeIds();
        if ($equipeIds !== null) {
            $query->whereIn('id', $equipeIds);
        }

        return $query->orderBy('nome')->get();
    }

    public function find(string $id): EccEquipe
    {
        $query = EccEquipe::query()
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->withCount('casais');

        $equipeIds = $this->visibility->restrictedEquipeIds();
        if ($equipeIds !== null) {
            $query->whereIn('id', $equipeIds);
        }

        return $query->findOrFail($id);
    }

    /**
     * @param  array{nome: string, cor?: string|null}  $data
     */
    public function create(array $data): EccEquipe
    {
        return EccEquipe::query()->create([
            'igreja_id' => $this->igrejaContext->current()->id,
            'nome' => Str::upper(trim($data['nome'])),
            'cor' => $data['cor'] ?? null,
        ]);
    }

    /**
     * @param  array{nome?: string, cor?: string|null}  $data
     */
    public function update(EccEquipe $equipe, array $data): EccEquipe
    {
        if (isset($data['nome'])) {
            $data['nome'] = Str::upper(trim($data['nome']));
        }

        $equipe->update($data);

        return $equipe->refresh()->loadCount('casais');
    }

    public function delete(EccEquipe $equipe): void
    {
        $equipe->delete();
    }

    public function findOrCreateByNome(string $nome): EccEquipe
    {
        $nome = Str::upper(trim($nome));
        $igrejaId = $this->igrejaContext->current()->id;

        return EccEquipe::query()->firstOrCreate(
            ['igreja_id' => $igrejaId, 'nome' => $nome],
            ['cor' => null],
        );
    }
}
