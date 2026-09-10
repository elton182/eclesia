<?php

declare(strict_types=1);

namespace App\Services;

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
