<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public const GUARD = 'web';

    /** @var list<string> */
    public const ROLES = [
        'admin-tenant',
        'admin-igreja',
        'coordenador-modulo',
        'secretaria',
        'lider-equipe',
        'membro',
    ];

    /** @var list<string> */
    public const MANAGEMENT_PERMISSIONS = [
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'roles.assign',
        'permissions.view',
    ];

    /** @var list<string> */
    public const ECC_STUB_PERMISSIONS = [
        'ecc.equipes.view',
        'ecc.equipes.manage',
        'ecc.casais.view',
        'ecc.casais.manage',
        'ecc.escala.editar',
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        setPermissionsTeamId(null);

        foreach ([...self::MANAGEMENT_PERMISSIONS, ...self::ECC_STUB_PERMISSIONS] as $name) {
            Permission::findOrCreate($name, self::GUARD);
        }

        foreach (self::ROLES as $roleName) {
            Role::findOrCreate($roleName, self::GUARD);
        }

        $adminTenant = Role::findByName('admin-tenant', self::GUARD);
        $adminTenant->syncPermissions(Permission::where('guard_name', self::GUARD)->get());

        $adminIgreja = Role::findByName('admin-igreja', self::GUARD);
        $adminIgreja->syncPermissions(self::MANAGEMENT_PERMISSIONS);

        $coordenador = Role::findByName('coordenador-modulo', self::GUARD);
        $coordenador->syncPermissions([
            'users.view',
            'permissions.view',
            ...self::ECC_STUB_PERMISSIONS,
        ]);

        $secretaria = Role::findByName('secretaria', self::GUARD);
        $secretaria->syncPermissions([
            'users.view',
            'users.create',
            'users.update',
            'roles.assign',
            'permissions.view',
            'ecc.casais.view',
            'ecc.casais.manage',
            'ecc.equipes.view',
        ]);

        $lider = Role::findByName('lider-equipe', self::GUARD);
        $lider->syncPermissions([
            'ecc.equipes.view',
            'ecc.casais.view',
            'ecc.escala.editar',
        ]);

        $membro = Role::findByName('membro', self::GUARD);
        $membro->syncPermissions([
            'ecc.equipes.view',
            'ecc.casais.view',
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
