<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Igreja;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IgrejaAccessService
{
    /**
     * @return Collection<int, string>
     */
    public function accessibleIds(SuperAdmin|User $actor): Collection
    {
        if ($actor instanceof SuperAdmin) {
            return Igreja::query()->orderBy('nome')->pluck('id');
        }

        $previous = getPermissionsTeamId();
        setPermissionsTeamId(null);
        $isAdminTenant = $actor->hasRole('admin-tenant');
        setPermissionsTeamId($previous);

        if ($isAdminTenant) {
            return Igreja::query()->orderBy('nome')->pluck('id');
        }

        $teamKey = config('permission.column_names.team_foreign_key', 'igreja_id');
        $table = config('permission.table_names.model_has_roles', 'model_has_roles');

        return DB::table($table)
            ->where('model_type', $actor->getMorphClass())
            ->where('model_id', $actor->getKey())
            ->whereNotNull($teamKey)
            ->distinct()
            ->orderBy($teamKey)
            ->pluck($teamKey)
            ->map(static fn ($id) => (string) $id)
            ->values();
    }

    public function canAccess(SuperAdmin|User $actor, string $igrejaId): bool
    {
        return $this->accessibleIds($actor)->contains($igrejaId);
    }
}
