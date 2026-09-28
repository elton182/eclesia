<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Casal;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Escopo de visibilidade ECC: líder de equipe só enxerga as equipes vinculadas.
 *
 * Quem tem ecc.*.manage (ou SuperAdmin / admin-tenant via Gate) vê a igreja inteira.
 */
class EccVisibilityScope
{
    public function __construct(private readonly IgrejaContext $igrejaContext) {}

    /**
     * IDs de equipes visíveis ao usuário autenticado, ou null = sem restrição.
     *
     * @return list<string>|null
     */
    public function restrictedEquipeIds(?Authenticatable $user = null): ?array
    {
        $user ??= auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        if ($this->userCan('ecc.casais.manage', $user) || $this->userCan('ecc.equipes.manage', $user)) {
            return null;
        }

        return $user->equipesLideradas()
            ->pluck('ecc_equipes.id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    public function userCan(string $permission, ?Authenticatable $user = null): bool
    {
        $user ??= auth()->user();

        if ($user instanceof SuperAdmin) {
            return true;
        }

        if (! $user instanceof User) {
            return false;
        }

        $previous = getPermissionsTeamId();

        try {
            // admin-tenant é papel de organização (team_id null)
            setPermissionsTeamId(null);
            $user->unsetRelation('roles')->unsetRelation('permissions');
            if ($user->hasRole('admin-tenant')) {
                return true;
            }

            try {
                setPermissionsTeamId($this->igrejaContext->current()->id);
            } catch (\Throwable) {
                setPermissionsTeamId(null);
            }

            $user->unsetRelation('roles')->unsetRelation('permissions');

            return $user->can($permission);
        } finally {
            setPermissionsTeamId($previous);
        }
    }

    /**
     * Foto e ficha: pessoa é cônjuge de casal numa equipe do líder.
     */
    public function canAtualizarPessoa(string $pessoaId): bool
    {
        if ($this->userCan('pessoas.manage') || $this->userCan('ecc.casais.manage')) {
            return true;
        }

        if (! $this->userCan('ecc.casais.atualizar')) {
            return false;
        }

        return $this->pessoaNaEquipeRestrita($pessoaId);
    }

    public function pessoaNaEquipeRestrita(string $pessoaId): bool
    {
        $ids = $this->restrictedEquipeIds();
        if ($ids === null || $ids === []) {
            return false;
        }

        try {
            $igrejaId = $this->igrejaContext->current()->id;
        } catch (\Throwable) {
            return false;
        }

        return Casal::query()
            ->where('igreja_id', $igrejaId)
            ->whereIn('ecc_equipe_id', $ids)
            ->where(function ($query) use ($pessoaId): void {
                $query->where('pessoa_a_id', $pessoaId)
                    ->orWhere('pessoa_b_id', $pessoaId);
            })
            ->exists();
    }

    public function assertCanAccessEquipe(?string $equipeId): void
    {
        $restricted = $this->restrictedEquipeIds();
        if ($restricted === null) {
            return;
        }

        if ($equipeId === null || $equipeId === '' || ! in_array($equipeId, $restricted, true)) {
            abort(404);
        }
    }
}
