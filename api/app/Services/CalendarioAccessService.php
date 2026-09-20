<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class CalendarioAccessService
{
    public function __construct(private readonly IgrejaContext $igrejaContext) {}

    public function canView(?Authenticatable $user = null): bool
    {
        return $this->userCan('calendario.colaborar', $user)
            || $this->userCan('calendario.gerir', $user);
    }

    public function canManage(?Authenticatable $user = null): bool
    {
        return $this->userCan('calendario.gerir', $user);
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
