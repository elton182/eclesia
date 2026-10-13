<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Pastoral;
use App\Models\User;
use Illuminate\Support\Collection;
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
}
