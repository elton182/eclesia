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
        'cadastros-usuarios',
        'cadastros-equipes',
        'cadastros-casais',
        'lider-equipe',
    ];

    /** Papéis visíveis/atribuíveis por usuários do tenant (sem SuperAdmin). */
    /** @var list<string> */
    public const ROLES_TENANT_UI = [
        'admin-igreja',
        'cadastros-usuarios',
        'cadastros-equipes',
        'cadastros-casais',
        'lider-equipe',
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

    /** Uma permissão por tela do shell (menu + rota). */
    /** @var list<string> */
    public const SCREEN_PERMISSIONS = [
        'telas.usuarios',
        'telas.equipes',
        'telas.casais',
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

        foreach ([
            ...self::MANAGEMENT_PERMISSIONS,
            ...self::SCREEN_PERMISSIONS,
            ...self::ECC_STUB_PERMISSIONS,
        ] as $name) {
            Permission::findOrCreate($name, self::GUARD);
        }

        foreach (self::ROLES as $roleName) {
            Role::findOrCreate($roleName, self::GUARD);
        }

        $adminTenant = Role::findByName('admin-tenant', self::GUARD);
        $adminTenant->syncPermissions(Permission::where('guard_name', self::GUARD)->get());

        $adminIgreja = Role::findByName('admin-igreja', self::GUARD);
        $adminIgreja->syncPermissions([
            ...self::MANAGEMENT_PERMISSIONS,
            ...self::SCREEN_PERMISSIONS,
            ...self::ECC_STUB_PERMISSIONS,
        ]);

        Role::findByName('cadastros-usuarios', self::GUARD)->syncPermissions([
            'telas.usuarios',
            'users.view',
            'users.create',
            'users.update',
            'permissions.view',
        ]);

        Role::findByName('cadastros-equipes', self::GUARD)->syncPermissions([
            'telas.equipes',
            'ecc.equipes.view',
            'ecc.equipes.manage',
        ]);

        Role::findByName('cadastros-casais', self::GUARD)->syncPermissions([
            'telas.casais',
            'ecc.casais.view',
            'ecc.casais.manage',
        ]);

        Role::findByName('lider-equipe', self::GUARD)->syncPermissions([
            'telas.equipes',
            'telas.casais',
            'ecc.equipes.view',
            'ecc.casais.view',
            'ecc.escala.editar',
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
