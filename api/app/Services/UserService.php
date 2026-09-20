<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EccEquipe;
use App\Models\Igreja;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return User::query()
            ->with('equipesLideradas:id,nome')
            ->orderBy('id')
            ->paginate($perPage);
    }

    /**
     * @param  array{name: string, email: string, password: string, is_active?: bool, pessoa_id?: string|null}  $data
     */
    public function create(array $data): User
    {
        if (User::emailExists($data['email'])) {
            throw ValidationException::withMessages([
                'email' => ['Este e-mail já está em uso.'],
            ]);
        }

        return User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'is_active' => $data['is_active'] ?? true,
            'pessoa_id' => $data['pessoa_id'] ?? null,
        ]);
    }

    /**
     * @param  array{name?: string, email?: string, password?: string, is_active?: bool, pessoa_id?: string|null}  $data
     */
    public function update(User $user, array $data): User
    {
        if (isset($data['email']) && User::emailExists($data['email'], $user->id)) {
            throw ValidationException::withMessages([
                'email' => ['Este e-mail já está em uso.'],
            ]);
        }

        $payload = collect($data)->only(['name', 'email', 'is_active', 'pessoa_id', 'password'])->filter(
            fn ($value, $key) => $key !== 'password' || ($value !== null && $value !== '')
        )->all();

        $user->fill($payload);
        $user->save();

        return $user->refresh();
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    /**
     * @return list<array{name: string, igreja_id: string|null}>
     */
    public function rolesFor(User $user): array
    {
        $rows = DB::table(config('permission.table_names.model_has_roles'))
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->where('model_has_roles.model_id', $user->getKey())
            ->select(['roles.name', 'model_has_roles.igreja_id'])
            ->get();

        return $rows->map(fn ($row) => [
            'name' => $row->name,
            'igreja_id' => $row->igreja_id,
        ])->values()->all();
    }

    /**
     * @return list<array{id: string, nome: string}>
     */
    public function equipesLideradasFor(User $user): array
    {
        return $user->equipesLideradas()
            ->get(['ecc_equipes.id', 'ecc_equipes.nome'])
            ->map(fn (EccEquipe $equipe) => [
                'id' => $equipe->id,
                'nome' => $equipe->nome,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{name: string, equipe_ids?: list<string>}>  $roles
     */
    public function syncRoles(User $user, ?string $igrejaId, array $roles, bool $actorIsSuperAdmin): User
    {
        $names = [];
        $liderEquipeIds = [];

        foreach ($roles as $index => $role) {
            $name = (string) ($role['name'] ?? '');
            $this->assertValidRole($name);

            if ($name === 'admin-tenant' && ! $actorIsSuperAdmin) {
                throw ValidationException::withMessages([
                    "roles.{$index}.name" => ['Apenas SuperAdmin pode gerenciar o papel admin-tenant.'],
                ]);
            }

            $names[] = $name;

            if ($name === 'lider-equipe') {
                $equipeIds = $role['equipe_ids'] ?? [];
                if (! is_array($equipeIds) || count($equipeIds) < 1) {
                    throw ValidationException::withMessages([
                        "roles.{$index}.equipe_ids" => ['Selecione ao menos uma equipe para o líder.'],
                    ]);
                }
                $liderEquipeIds = array_values(array_unique(array_map('strval', $equipeIds)));
            }
        }

        $churchRoles = array_values(array_intersect($names, RolesAndPermissionsSeeder::ROLES_TENANT_UI));
        $orgRoles = array_values(array_intersect($names, RolesAndPermissionsSeeder::ROLES_TENANT_ORG));
        $wantsAdminTenant = in_array('admin-tenant', $names, true);

        if ($churchRoles !== [] && ($igrejaId === null || $igrejaId === '')) {
            throw ValidationException::withMessages([
                'igreja_id' => ['A igreja é obrigatória para papéis de igreja.'],
            ]);
        }

        if ($igrejaId !== null && $igrejaId !== '' && ! Igreja::query()->whereKey($igrejaId)->exists()) {
            throw ValidationException::withMessages([
                'igreja_id' => ['Igreja não encontrada.'],
            ]);
        }

        if ($liderEquipeIds !== []) {
            $this->assertEquipesBelongToIgreja($liderEquipeIds, (string) $igrejaId);
        }

        if ($igrejaId !== null && $igrejaId !== '') {
            setPermissionsTeamId($igrejaId);
            $user->syncRoles($churchRoles);

            if (in_array('lider-equipe', $churchRoles, true)) {
                $user->equipesLideradas()->sync($liderEquipeIds);
            } else {
                $user->equipesLideradas()->detach();
            }
        } elseif ($churchRoles === []) {
            // sem papéis de igreja neste sync: não altera teams de igreja
        }

        setPermissionsTeamId(null);
        $user->unsetRelation('roles');
        $hadAdminTenant = $user->hasRole('admin-tenant');
        $finalOrg = $orgRoles;
        if ($actorIsSuperAdmin) {
            if ($wantsAdminTenant) {
                $finalOrg[] = 'admin-tenant';
            }
        } elseif ($hadAdminTenant) {
            $finalOrg[] = 'admin-tenant';
        }
        $user->syncRoles(array_values(array_unique($finalOrg)));

        setPermissionsTeamId(null);

        app(AuditLogger::class)->log(
            action: AuditLogger::ACTION_ROLES_SYNCED,
            auditable: $user,
            newValues: [
                'igreja_id' => $igrejaId,
                'roles' => $names,
                'equipe_ids' => $liderEquipeIds,
            ],
            auditableLabel: $user->name,
        );

        return $user->refresh()->load('equipesLideradas');
    }

    /**
     * @param  list<string>|null  $equipeIds
     */
    public function assignRole(
        User $user,
        string $roleName,
        ?string $igrejaId,
        ?array $equipeIds,
        bool $actorIsSuperAdmin
    ): User {
        $this->assertValidRole($roleName);

        if ($roleName === 'admin-tenant') {
            if (! $actorIsSuperAdmin) {
                throw ValidationException::withMessages([
                    'role' => ['Apenas SuperAdmin pode gerenciar o papel admin-tenant.'],
                ]);
            }
            setPermissionsTeamId(null);
            $user->assignRole($roleName);

            return $user->refresh();
        }

        if (in_array($roleName, RolesAndPermissionsSeeder::ROLES_TENANT_ORG, true)) {
            setPermissionsTeamId(null);
            $user->assignRole($roleName);
            setPermissionsTeamId(null);

            return $user->refresh();
        }

        if ($igrejaId === null || $igrejaId === '') {
            throw ValidationException::withMessages([
                'igreja_id' => ['A igreja é obrigatória para este papel.'],
            ]);
        }

        if (! Igreja::query()->whereKey($igrejaId)->exists()) {
            throw ValidationException::withMessages([
                'igreja_id' => ['Igreja não encontrada.'],
            ]);
        }

        if ($roleName === 'lider-equipe') {
            $ids = array_values(array_unique(array_map('strval', $equipeIds ?? [])));
            if ($ids === []) {
                throw ValidationException::withMessages([
                    'equipe_ids' => ['Selecione ao menos uma equipe para o líder.'],
                ]);
            }
            $this->assertEquipesBelongToIgreja($ids, $igrejaId);
            setPermissionsTeamId($igrejaId);
            $user->assignRole($roleName);
            $user->equipesLideradas()->sync($ids);
        } else {
            setPermissionsTeamId($igrejaId);
            $user->assignRole($roleName);
        }

        setPermissionsTeamId(null);

        return $user->refresh()->load('equipesLideradas');
    }

    public function removeRole(User $user, string $roleName, ?string $igrejaId, bool $actorIsSuperAdmin): User
    {
        $this->assertValidRole($roleName);

        if ($roleName === 'admin-tenant') {
            if (! $actorIsSuperAdmin) {
                throw ValidationException::withMessages([
                    'role' => ['Apenas SuperAdmin pode gerenciar o papel admin-tenant.'],
                ]);
            }
            setPermissionsTeamId(null);
            $user->removeRole($roleName);

            return $user->refresh();
        }

        if (in_array($roleName, RolesAndPermissionsSeeder::ROLES_TENANT_ORG, true)) {
            setPermissionsTeamId(null);
            $user->removeRole($roleName);
            setPermissionsTeamId(null);

            return $user->refresh();
        }

        if ($igrejaId === null || $igrejaId === '') {
            throw ValidationException::withMessages([
                'igreja_id' => ['A igreja é obrigatória para este papel.'],
            ]);
        }

        setPermissionsTeamId($igrejaId);
        $user->removeRole($roleName);

        if ($roleName === 'lider-equipe') {
            $user->equipesLideradas()->detach();
        }

        setPermissionsTeamId(null);

        return $user->refresh();
    }

    /**
     * @param  list<string>  $equipeIds
     */
    private function assertEquipesBelongToIgreja(array $equipeIds, string $igrejaId): void
    {
        $count = EccEquipe::query()
            ->whereIn('id', $equipeIds)
            ->where('igreja_id', $igrejaId)
            ->count();

        if ($count !== count($equipeIds)) {
            throw ValidationException::withMessages([
                'equipe_ids' => ['Uma ou mais equipes são inválidas para a igreja selecionada.'],
            ]);
        }
    }

    private function assertValidRole(string $roleName): void
    {
        if (! in_array($roleName, RolesAndPermissionsSeeder::ROLES, true)) {
            throw ValidationException::withMessages([
                'role' => ['Papel inválido.'],
            ]);
        }

        setPermissionsTeamId(null);
        if (! Role::where('name', $roleName)->where('guard_name', 'web')->exists()) {
            throw ValidationException::withMessages([
                'role' => ['Papel não encontrado no tenant.'],
            ]);
        }
    }
}
