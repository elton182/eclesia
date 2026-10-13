<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Pastoral;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class PlanejamentoAccessService
{
    public function __construct(private readonly IgrejaContext $igrejaContext) {}

    public function canView(?Authenticatable $user = null): bool
    {
        return $this->userCan('planejamento.gerir', $user)
            || $this->userCan('planejamento.propor', $user)
            || $this->userCan('telas.planejamento', $user);
    }

    public function canManage(?Authenticatable $user = null): bool
    {
        return $this->userCan('planejamento.gerir', $user);
    }

    public function canPropose(?Authenticatable $user = null): bool
    {
        return $this->userCan('planejamento.propor', $user);
    }

    /**
     * IDs das pastorais vinculadas ao usuário (vazio se gerir / SuperAdmin / admin-tenant).
     *
     * @return list<string>|null null = acesso a todas; list = restrito
     */
    public function scopedPastoralIds(?Authenticatable $user = null): ?array
    {
        if ($this->canManage($user)) {
            return null;
        }

        $user ??= auth()->user();

        if (! $user instanceof User || ! $this->canPropose($user)) {
            return [];
        }

        /** @var Collection<int, string> $ids */
        $ids = $user->pastorais()->pluck('pastorais.id');

        return $ids->values()->all();
    }

    public function isLinkedToPastoral(string $pastoralId, ?Authenticatable $user = null): bool
    {
        if ($this->canManage($user)) {
            return true;
        }

        $scoped = $this->scopedPastoralIds($user);

        return $scoped !== null && in_array($pastoralId, $scoped, true);
    }

    public function findPastoralOfIgreja(string $pastoralId): Pastoral
    {
        return Pastoral::query()
            ->where('igreja_id', $this->igrejaContext->current()->id)
            ->whereKey($pastoralId)
            ->firstOrFail();
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
}
