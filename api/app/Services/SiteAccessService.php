<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

class SiteAccessService
{
    public function __construct(
        private readonly IgrejaAccessService $igrejas,
    ) {}

    public function isOrgSiteManager(User $user): bool
    {
        $previous = getPermissionsTeamId();
        setPermissionsTeamId(null);
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');
        $ok = $user->hasRole('admin-tenant')
            || $user->hasRole('gestor-site')
            || $user->can('site.settings.update')
            || $user->can('site.pages.manage');
        setPermissionsTeamId($previous);

        return $ok;
    }

    public function canManageComunicado(User $user, ?string $igrejaId): bool
    {
        if ($this->isOrgSiteManager($user)) {
            return true;
        }

        if ($igrejaId === null || $igrejaId === '') {
            return $this->isOrgSiteManager($user);
        }

        if (! $this->igrejas->canAccess($user, $igrejaId)) {
            return false;
        }

        $previous = getPermissionsTeamId();
        setPermissionsTeamId($igrejaId);
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');
        $ok = $user->can('site.comunicados.manage');
        setPermissionsTeamId($previous);

        return $ok;
    }

    public function canManagePastoral(User $user, string $igrejaId): bool
    {
        if ($this->isOrgSiteManager($user)) {
            return true;
        }

        if (! $this->igrejas->canAccess($user, $igrejaId)) {
            return false;
        }

        $previous = getPermissionsTeamId();
        setPermissionsTeamId($igrejaId);
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');
        $ok = $user->can('site.pastorais.manage');
        setPermissionsTeamId($previous);

        return $ok;
    }

    public function canViewComunicados(User $user): bool
    {
        return $user->can('site.comunicados.view') || $this->isOrgSiteManager($user);
    }

    public function canViewPastorais(User $user): bool
    {
        return $user->can('site.pastorais.view') || $this->isOrgSiteManager($user);
    }
}
