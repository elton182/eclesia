<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Igreja;
use App\Models\User;
use App\Services\IgrejaAccessService;

class IgrejaPolicy
{
    public function __construct(
        private readonly IgrejaAccessService $access,
    ) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Igreja $igreja): bool
    {
        return $this->access->canAccess($user, $igreja->id);
    }

    public function create(User $user): bool
    {
        return $user->can('igrejas.create');
    }

    public function update(User $user, Igreja $igreja): bool
    {
        if (! $this->access->canAccess($user, $igreja->id)) {
            return false;
        }

        $previous = getPermissionsTeamId();
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');

        setPermissionsTeamId(null);
        if ($user->hasRole('admin-tenant')) {
            setPermissionsTeamId($previous);

            return true;
        }

        setPermissionsTeamId($igreja->id);
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');
        $allowed = $user->can('igrejas.update');
        setPermissionsTeamId($previous);

        return $allowed;
    }

    public function delete(User $user, Igreja $igreja): bool
    {
        return $user->can('igrejas.delete');
    }
}
