<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Pastoral;
use App\Models\PlanejamentoAnual;
use App\Models\PlanejamentoEvento;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class PlanejamentoAccessService
{
    public function __construct(private readonly IgrejaContext $igrejaContext) {}

    public function canView(?Authenticatable $user = null): bool
    {
        return $this->canGerir($user)
            || $this->canPropor($user)
            || $this->userCan('planejamento.ver_global', $user);
    }

    public function canGerir(?Authenticatable $user = null): bool
    {
        return $this->userCan('planejamento.gerir', $user);
    }

    public function canPropor(?Authenticatable $user = null): bool
    {
        return $this->userCan('planejamento.propor', $user);
    }

    public function canVerGlobal(?Authenticatable $user = null): bool
    {
        return $this->canGerir($user) || $this->userCan('planejamento.ver_global', $user);
    }

    /**
     * IDs das pastorais do usuário no pivot (igreja atual).
     *
     * @return list<string>
     */
    public function pastoralIdsDoUsuario(?Authenticatable $user = null): array
    {
        $user ??= auth()->user();
        if (! $user instanceof User) {
            return [];
        }

        $igrejaId = $this->igrejaContext->current()->id;

        return Pastoral::query()
            ->where('igreja_id', $igrejaId)
            ->whereHas('membros', fn ($q) => $q->where('users.id', $user->id))
            ->pluck('id')
            ->all();
    }

    public function pertencePastoral(string $pastoralId, ?Authenticatable $user = null): bool
    {
        return in_array($pastoralId, $this->pastoralIdsDoUsuario($user), true);
    }

    public function canCreateEvento(PlanejamentoAnual $anual, string $pastoralId, ?Authenticatable $user = null): bool
    {
        if ($this->canGerir($user)) {
            return in_array($anual->status, [
                PlanejamentoAnual::STATUS_RASCUNHO,
                PlanejamentoAnual::STATUS_COLETA,
                PlanejamentoAnual::STATUS_REVISAO,
            ], true);
        }

        if (! $this->canPropor($user) || ! $this->pertencePastoral($pastoralId, $user)) {
            return false;
        }

        return $anual->status === PlanejamentoAnual::STATUS_COLETA;
    }

    public function canEditEvento(PlanejamentoEvento $evento, ?Authenticatable $user = null): bool
    {
        $anual = $evento->planejamentoAnual ?? $evento->planejamentoAnual()->first();
        if ($anual === null) {
            return false;
        }

        if ($this->canGerir($user)) {
            return ! $anual->isFechado();
        }

        if (! $this->canPropor($user) || ! $this->pertencePastoral($evento->pastoral_id, $user)) {
            return false;
        }

        if ($anual->status === PlanejamentoAnual::STATUS_COLETA) {
            return true;
        }

        if ($anual->status === PlanejamentoAnual::STATUS_REVISAO) {
            return $evento->status_solicitacao === PlanejamentoEvento::STATUS_AJUSTE_SOLICITADO;
        }

        return false;
    }

    public function canDeleteEvento(PlanejamentoEvento $evento, ?Authenticatable $user = null): bool
    {
        return $this->canEditEvento($evento, $user);
    }

    /**
     * @return Collection<int, string>|null null = sem filtro (vê todas)
     */
    public function pastoralIdsFiltroListagem(?Authenticatable $user = null): ?Collection
    {
        if ($this->canVerGlobal($user)) {
            return null;
        }

        return collect($this->pastoralIdsDoUsuario($user));
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
