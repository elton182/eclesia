<?php

declare(strict_types=1);

namespace App\Services;

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

    public function assignRole(User $user, string $roleName, ?string $igrejaId): User
    {
        $this->assertValidRole($roleName);

        if ($roleName === 'admin-tenant') {
            setPermissionsTeamId(null);
            $user->assignRole($roleName);

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

        setPermissionsTeamId($igrejaId);
        $user->assignRole($roleName);

        return $user->refresh();
    }

    public function removeRole(User $user, string $roleName, ?string $igrejaId): User
    {
        $this->assertValidRole($roleName);

        if ($roleName === 'admin-tenant') {
            setPermissionsTeamId(null);
            $user->removeRole($roleName);

            return $user->refresh();
        }

        if ($igrejaId === null || $igrejaId === '') {
            throw ValidationException::withMessages([
                'igreja_id' => ['A igreja é obrigatória para este papel.'],
            ]);
        }

        setPermissionsTeamId($igrejaId);
        $user->removeRole($roleName);

        return $user->refresh();
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
