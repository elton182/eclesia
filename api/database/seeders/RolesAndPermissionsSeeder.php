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
        'gestor-site',
        'admin-igreja',
        'cadastros-usuarios',
        'cadastros-equipes',
        'cadastros-casais',
        'cadastros-eventos',
        'cadastros-calendario',
        'lider-equipe',
    ];

    /** Papéis visíveis/atribuíveis por usuários do tenant (sem SuperAdmin). */
    /** @var list<string> */
    public const ROLES_TENANT_UI = [
        'admin-igreja',
        'cadastros-usuarios',
        'cadastros-equipes',
        'cadastros-casais',
        'cadastros-eventos',
        'cadastros-calendario',
        'lider-equipe',
    ];

    /** Papéis de organização (team_id null), atribuíveis por admin-tenant. */
    /** @var list<string> */
    public const ROLES_TENANT_ORG = [
        'gestor-site',
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
    public const AUDITORIA_PERMISSIONS = [
        'auditoria.view',
    ];

    /** Uma permissão por tela do shell (menu + rota). */
    /** @var list<string> */
    public const SCREEN_PERMISSIONS = [
        'telas.usuarios',
        'telas.equipes',
        'telas.casais',
        'telas.eventos',
        'telas.igrejas',
        'telas.site',
        'telas.calendario',
        'telas.auditoria',
        'telas.financeiro',
    ];

    /** @var list<string> */
    public const CALENDARIO_PERMISSIONS = [
        'calendario.gerir',
        'calendario.colaborar',
    ];

    /** @var list<string> */
    public const IGREJA_PERMISSIONS = [
        'igrejas.view',
        'igrejas.create',
        'igrejas.update',
        'igrejas.delete',
    ];

    /** @var list<string> */
    public const ECC_STUB_PERMISSIONS = [
        'ecc.equipes.view',
        'ecc.equipes.manage',
        'ecc.casais.view',
        'ecc.casais.manage',
        'ecc.eventos.view',
        'ecc.eventos.manage',
        'ecc.financeiro.view',
        'ecc.financeiro.manage',
        'ecc.escala.editar',
        'pessoas.manage',
    ];

    /** @var list<string> */
    public const SITE_PERMISSIONS = [
        'site.settings.view',
        'site.settings.update',
        'site.pages.view',
        'site.pages.manage',
        'site.comunicados.view',
        'site.comunicados.manage',
        'site.pastorais.view',
        'site.pastorais.manage',
        'site.forms.view',
        'site.forms.manage',
        'site.forms.submissions.view',
        'site.igrejas.publish',
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        setPermissionsTeamId(null);

        foreach ([
            ...self::MANAGEMENT_PERMISSIONS,
            ...self::SCREEN_PERMISSIONS,
            ...self::AUDITORIA_PERMISSIONS,
            ...self::IGREJA_PERMISSIONS,
            ...self::ECC_STUB_PERMISSIONS,
            ...self::CALENDARIO_PERMISSIONS,
            ...self::SITE_PERMISSIONS,
        ] as $name) {
            Permission::findOrCreate($name, self::GUARD);
        }

        foreach (self::ROLES as $roleName) {
            Role::findOrCreate($roleName, self::GUARD);
        }

        $adminTenant = Role::findByName('admin-tenant', self::GUARD);
        $adminTenant->syncPermissions(Permission::where('guard_name', self::GUARD)->get());

        Role::findByName('gestor-site', self::GUARD)->syncPermissions([
            'telas.site',
            ...self::SITE_PERMISSIONS,
        ]);

        $adminIgreja = Role::findByName('admin-igreja', self::GUARD);
        $adminIgreja->syncPermissions([
            ...self::MANAGEMENT_PERMISSIONS,
            ...self::SCREEN_PERMISSIONS,
            ...self::AUDITORIA_PERMISSIONS,
            ...self::ECC_STUB_PERMISSIONS,
            ...self::CALENDARIO_PERMISSIONS,
            'igrejas.view',
            'igrejas.update',
            'telas.site',
            'site.comunicados.view',
            'site.comunicados.manage',
            'site.pastorais.view',
            'site.pastorais.manage',
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
            'pessoas.manage',
        ]);

        Role::findByName('cadastros-eventos', self::GUARD)->syncPermissions([
            'telas.eventos',
            'ecc.eventos.view',
            'ecc.eventos.manage',
        ]);

        Role::findByName('cadastros-calendario', self::GUARD)->syncPermissions([
            'telas.calendario',
            'calendario.gerir',
            'calendario.colaborar',
        ]);

        Role::findByName('lider-equipe', self::GUARD)->syncPermissions([
            'telas.equipes',
            'telas.casais',
            'telas.eventos',
            'ecc.equipes.view',
            'ecc.casais.view',
            'ecc.eventos.view',
            'ecc.escala.editar',
            'telas.calendario',
            'calendario.colaborar',
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
