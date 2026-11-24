<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Pastoral;
use App\Models\PlanejamentoEvento;
use App\Models\PlanejamentoPastoralToken;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PastoralService
{
    public function __construct(private readonly IgrejaContext $igrejaContext) {}

    public function list(): Collection
    {
        return Pastoral::query()
            ->with(['igreja', 'membros'])
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get();
    }

    /**
     * @param  array{nome: string, descricao_publica?: ?string, contato_publico?: ?string, ordem?: int, publicado_no_site?: bool, ativa?: bool}  $data
     */
    public function create(array $data): Pastoral
    {
        return Pastoral::query()->create([
            'igreja_id' => $this->igrejaContext->current()->id,
            'nome' => $data['nome'],
            'descricao_publica' => $data['descricao_publica'] ?? null,
            'contato_publico' => $data['contato_publico'] ?? null,
            'ordem' => $data['ordem'] ?? 0,
            'publicado_no_site' => $data['publicado_no_site'] ?? false,
            'ativa' => $data['ativa'] ?? true,
        ])->load(['igreja', 'membros']);
    }

    public function find(string $id): Pastoral
    {
        return Pastoral::query()
            ->with(['igreja', 'membros'])
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->whereKey($id)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Pastoral $pastoral, array $data): Pastoral
    {
        $pastoral->fill($data);
        $pastoral->save();

        return $pastoral->refresh()->load(['igreja', 'membros']);
    }

    public function delete(Pastoral $pastoral): void
    {
        $pastoral->delete();
    }

    public function listMembros(Pastoral $pastoral): Collection
    {
        return $pastoral->membros()->get();
    }

    /**
     * @param  array{user_id: string, papel: string}  $data
     */
    public function attachMembro(Pastoral $pastoral, array $data): User
    {
        $user = User::query()->where('ulid', $data['user_id'])->first();
        if ($user === null) {
            throw ValidationException::withMessages([
                'user_id' => ['Usuário não encontrado.'],
            ]);
        }

        if ($pastoral->membros()->where('users.id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => ['Usuário já vinculado a esta pastoral.'],
            ]);
        }

        $pastoral->membros()->attach($user->id, ['papel' => $data['papel']]);

        return $user->fresh()->load([]);
    }

    public function detachMembro(Pastoral $pastoral, string $userUlid): void
    {
        $user = User::query()->where('ulid', $userUlid)->firstOrFail();
        $pastoral->membros()->detach($user->id);
    }

    /**
     * Unifica duas pastorais (SPEC-038): mantém keep, absorve absorb.
     */
    public function merge(string $keepId, string $absorbId): Pastoral
    {
        if ($keepId === $absorbId) {
            throw ValidationException::withMessages([
                'absorb_id' => ['Selecione duas pastorais diferentes para unificar.'],
            ]);
        }

        return DB::transaction(function () use ($keepId, $absorbId) {
            $keep = $this->find($keepId);
            $absorb = $this->find($absorbId);

            $this->mergeMembros($keep, $absorb);
            $this->reatribuirEventos($keep, $absorb);
            $this->reatribuirTokens($keep, $absorb);
            $this->preencherLacunas($keep, $absorb);

            $absorb->membros()->detach();
            $absorb->delete();

            return $this->find((string) $keep->id);
        });
    }

    private function mergeMembros(Pastoral $keep, Pastoral $absorb): void
    {
        $keepByUser = $keep->membros()
            ->get()
            ->keyBy(fn (User $u) => (int) $u->id);

        foreach ($absorb->membros()->get() as $user) {
            $papelAbsorb = (string) ($user->pivot->papel ?? Pastoral::PAPEL_MEMBRO);
            $existente = $keepByUser->get((int) $user->id);

            if ($existente === null) {
                $keep->membros()->attach($user->id, ['papel' => $papelAbsorb]);
                continue;
            }

            $papelKeep = (string) ($existente->pivot->papel ?? Pastoral::PAPEL_MEMBRO);
            if (
                $papelKeep !== Pastoral::PAPEL_COORDENADOR
                && $papelAbsorb === Pastoral::PAPEL_COORDENADOR
            ) {
                $keep->membros()->updateExistingPivot($user->id, [
                    'papel' => Pastoral::PAPEL_COORDENADOR,
                ]);
            }
        }
    }

    private function reatribuirEventos(Pastoral $keep, Pastoral $absorb): void
    {
        PlanejamentoEvento::query()
            ->where('pastoral_id', $absorb->id)
            ->update(['pastoral_id' => $keep->id]);
    }

    private function reatribuirTokens(Pastoral $keep, Pastoral $absorb): void
    {
        $tokensAbsorb = PlanejamentoPastoralToken::query()
            ->where('pastoral_id', $absorb->id)
            ->get();

        foreach ($tokensAbsorb as $token) {
            $conflito = PlanejamentoPastoralToken::query()
                ->where('planejamento_anual_id', $token->planejamento_anual_id)
                ->where('pastoral_id', $keep->id)
                ->exists();

            if ($conflito) {
                $token->delete();
                continue;
            }

            $token->pastoral_id = $keep->id;
            $token->save();
        }
    }

    private function preencherLacunas(Pastoral $keep, Pastoral $absorb): void
    {
        $dirty = false;
        foreach (['descricao_publica', 'contato_publico'] as $campo) {
            $atual = $keep->{$campo};
            $candidato = $absorb->{$campo};
            if (($atual === null || $atual === '') && $candidato !== null && $candidato !== '') {
                $keep->{$campo} = $candidato;
                $dirty = true;
            }
        }
        if ($dirty) {
            $keep->save();
        }
    }
}
