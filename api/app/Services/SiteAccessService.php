<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SuperAdmin;
use App\Models\User;

class SiteAccessService
{
    public function __construct(
        private readonly IgrejaAccessService $igrejas,
    ) {}

    public function isOrgSiteManager(SuperAdmin|User $actor): bool
    {
        if ($actor instanceof SuperAdmin) {
            return true;
        }

        $previous = getPermissionsTeamId();
        setPermissionsTeamId(null);
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');
        $ok = $actor->hasRole('admin-tenant')
            || $actor->hasRole('gestor-site')
            || $actor->can('site.settings.update')
            || $actor->can('site.pages.manage');
        setPermissionsTeamId($previous);

        return $ok;
    }

    public function canManageComunicado(SuperAdmin|User $actor, ?string $igrejaId): bool
    {
        if ($this->isOrgSiteManager($actor)) {
            return true;
        }

        if ($igrejaId === null || $igrejaId === '') {
            return false;
        }

        if (! $this->igrejas->canAccess($actor, $igrejaId)) {
            return false;
        }

        $previous = getPermissionsTeamId();
        setPermissionsTeamId($igrejaId);
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');
        $ok = $actor->can('site.comunicados.manage');
        setPermissionsTeamId($previous);

        return $ok;
    }

    public function canManagePastoral(SuperAdmin|User $actor, string $igrejaId): bool
    {
        if ($this->isOrgSiteManager($actor)) {
            return true;
        }

        if (! $this->igrejas->canAccess($actor, $igrejaId)) {
            return false;
        }

        $previous = getPermissionsTeamId();
        setPermissionsTeamId($igrejaId);
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');
        $ok = $actor->can('site.pastorais.manage');
        setPermissionsTeamId($previous);

        return $ok;
    }

    public function canViewComunicados(SuperAdmin|User $actor): bool
    {
        if ($actor instanceof SuperAdmin) {
            return true;
        }

        return $actor->can('site.comunicados.view') || $this->isOrgSiteManager($actor);
    }

    public function canViewPastorais(SuperAdmin|User $actor): bool
    {
        if ($actor instanceof SuperAdmin) {
            return true;
        }

        return $actor->can('site.pastorais.view') || $this->isOrgSiteManager($actor);
    }
}
