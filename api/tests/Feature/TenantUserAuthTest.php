<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Igreja;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantUserAuthTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $platform = SuperAdmin::query()->create([
            'name' => 'Admin',
            'email' => 'platform@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($platform);

        $response = $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Paróquia Teste',
            'slug' => 'paroquia-teste',
            'aliases' => ['ptest'],
        ]);
        $response->assertCreated();
        $this->tenant = Tenant::query()->where('slug', 'paroquia-teste')->firstOrFail();

        Auth::forgetGuards();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        $dbPath = database_path('tenant'.$this->tenant->id);
        if (File::exists($dbPath)) {
            File::delete($dbPath);
        }
        parent::tearDown();
    }

    public function test_login_with_slug(): void
    {
        $this->postJson('/api/v1/web/login', [
            'tenant' => 'paroquia-teste',
            'email' => 'admin@paroquia-teste.local',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('tenant.slug', 'paroquia-teste')
            ->assertJsonStructure(['access_token', 'user', 'tenant']);
    }

    public function test_login_with_alias(): void
    {
        $this->postJson('/api/v1/web/login', [
            'tenant' => 'ptest',
            'email' => 'admin@paroquia-teste.local',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('tenant.slug', 'paroquia-teste');
    }

    public function test_login_with_name(): void
    {
        $this->postJson('/api/v1/web/login', [
            'tenant' => 'Paróquia Teste',
            'email' => 'admin@paroquia-teste.local',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('tenant.slug', 'paroquia-teste');
    }

    public function test_login_rejects_bad_password(): void
    {
        $this->postJson('/api/v1/web/login', [
            'tenant' => 'paroquia-teste',
            'email' => 'admin@paroquia-teste.local',
            'password' => 'wrong',
        ])->assertForbidden();
    }

    public function test_refresh_renova_access_token(): void
    {
        $login = $this->postJson('/api/v1/web/login', [
            'tenant' => 'paroquia-teste',
            'email' => 'admin@paroquia-teste.local',
            'password' => 'password',
        ])->assertOk();

        $refresh = $login->getCookie('refresh_token', false)?->getValue();
        $this->assertNotEmpty($refresh);

        $renewed = $this->withHeader('X-Tenant', 'paroquia-teste')
            ->postJson('/api/v1/web/refresh', ['refresh_token' => $refresh])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['access_token']);

        $this->assertNotSame($login->json('access_token'), $renewed->json('access_token'));

        $this->withHeader('X-Tenant', 'paroquia-teste')
            ->withHeader('Authorization', 'Bearer '.$renewed->json('access_token'))
            ->postJson('/api/v1/web/me')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_login_unknown_tenant(): void
    {
        $this->postJson('/api/v1/web/login', [
            'tenant' => 'nao-existe',
            'email' => 'admin@paroquia-teste.local',
            'password' => 'password',
        ])->assertNotFound();
    }

    public function test_admin_can_manage_users_and_roles(): void
    {
        tenancy()->initialize($this->tenant);
        $admin = User::findByEmail('admin@paroquia-teste.local');
        $this->assertNotNull($admin);
        $igrejaId = Igreja::query()->firstOrFail()->id;
        tenancy()->end();

        Sanctum::actingAs($admin);

        $create = $this->withHeader('X-Tenant', 'paroquia-teste')
            ->postJson('/api/v1/users', [
                'name' => 'Cadastros User',
                'email' => 'cad@paroquia-teste.local',
                'password' => 'password123',
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.email', 'cad@paroquia-teste.local');

        $userId = $create->json('data.id');

        $this->withHeader('X-Tenant', 'paroquia-teste')
            ->putJson("/api/v1/users/{$userId}/roles", [
                'igreja_id' => $igrejaId,
                'roles' => [
                    ['name' => 'cadastros-usuarios'],
                    ['name' => 'admin-igreja'],
                ],
            ])
            ->assertOk()
            ->assertJsonFragment(['name' => 'cadastros-usuarios'])
            ->assertJsonFragment(['name' => 'admin-igreja']);

        $roles = $this->withHeader('X-Tenant', 'paroquia-teste')
            ->getJson('/api/v1/roles')
            ->assertOk()
            ->json('data');

        $roleNames = collect($roles)->pluck('name')->all();
        $this->assertContains('cadastros-usuarios', $roleNames);
        $this->assertContains('cadastros-equipes', $roleNames);
        $this->assertContains('cadastros-casais', $roleNames);
        $this->assertContains('admin-igreja', $roleNames);
        $this->assertContains('lider-equipe', $roleNames);
        $this->assertNotContains('admin-tenant', $roleNames);

        $this->withHeader('X-Tenant', 'paroquia-teste')
            ->getJson('/api/v1/permissions')
            ->assertOk()
            ->assertJsonFragment(['name' => 'users.view']);

        $this->withHeader('X-Tenant', 'paroquia-teste')
            ->getJson('/api/v1/users')
            ->assertOk();
    }

    public function test_sync_lider_equipe_requires_equipe_ids(): void
    {
        tenancy()->initialize($this->tenant);
        $admin = User::findByEmail('admin@paroquia-teste.local');
        $igrejaId = Igreja::query()->firstOrFail()->id;
        tenancy()->end();

        Sanctum::actingAs($admin);

        $userId = $this->withHeader('X-Tenant', 'paroquia-teste')
            ->postJson('/api/v1/users', [
                'name' => 'Líder',
                'email' => 'lider@paroquia-teste.local',
                'password' => 'password123',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withHeader('X-Tenant', 'paroquia-teste')
            ->putJson("/api/v1/users/{$userId}/roles", [
                'igreja_id' => $igrejaId,
                'roles' => [
                    ['name' => 'lider-equipe'],
                ],
            ])
            ->assertStatus(422);
    }

    public function test_sync_lider_equipe_links_multiple_equipes(): void
    {
        tenancy()->initialize($this->tenant);
        $admin = User::findByEmail('admin@paroquia-teste.local');
        $igrejaId = Igreja::query()->firstOrFail()->id;
        tenancy()->end();

        Sanctum::actingAs($admin);

        $eq1 = $this->withHeader('X-Tenant', 'paroquia-teste')
            ->postJson('/api/v1/ecc/equipes', ['nome' => 'Equipe Alpha', 'cor' => '#111111'])
            ->assertCreated()
            ->json('data.id');

        $eq2 = $this->withHeader('X-Tenant', 'paroquia-teste')
            ->postJson('/api/v1/ecc/equipes', ['nome' => 'Equipe Beta', 'cor' => '#222222'])
            ->assertCreated()
            ->json('data.id');

        $userId = $this->withHeader('X-Tenant', 'paroquia-teste')
            ->postJson('/api/v1/users', [
                'name' => 'Líder Multi',
                'email' => 'lider-multi@paroquia-teste.local',
                'password' => 'password123',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withHeader('X-Tenant', 'paroquia-teste')
            ->putJson("/api/v1/users/{$userId}/roles", [
                'igreja_id' => $igrejaId,
                'roles' => [
                    [
                        'name' => 'lider-equipe',
                        'equipe_ids' => [$eq1, $eq2],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonFragment(['name' => 'lider-equipe'])
            ->assertJsonPath('data.equipes_lideradas.0.id', $eq1)
            ->assertJsonPath('data.equipes_lideradas.1.id', $eq2);
    }

    public function test_login_then_me_with_bearer_token(): void
    {
        $login = $this->postJson('/api/v1/web/login', [
            'tenant' => 'paroquia-teste',
            'email' => 'admin@paroquia-teste.local',
            'password' => 'password',
        ])->assertOk();

        $token = $login->json('access_token');
        $this->assertNotEmpty($token);

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Tenant', 'paroquia-teste')
            ->postJson('/api/v1/web/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('email', 'admin@paroquia-teste.local');
    }

    public function test_users_require_auth(): void
    {
        $this->withHeader('X-Tenant', 'paroquia-teste')
            ->getJson('/api/v1/users')
            ->assertUnauthorized();
    }

    public function test_super_admin_can_list_tenant_users(): void
    {
        $platform = SuperAdmin::query()->create([
            'name' => 'Platform',
            'email' => 'platform-users@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($platform);

        $this->withHeader('X-Tenant', 'paroquia-teste')
            ->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_super_admin_sees_admin_tenant_role_in_catalog(): void
    {
        $platform = SuperAdmin::query()->create([
            'name' => 'Platform Roles',
            'email' => 'platform-roles@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($platform);

        $this->withHeader('X-Tenant', 'paroquia-teste')
            ->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonFragment(['name' => 'admin-tenant'])
            ->assertJsonFragment(['name' => 'cadastros-usuarios']);
    }

    public function test_super_admin_bearer_can_list_users_and_ecc(): void
    {
        $platform = SuperAdmin::query()->create([
            'name' => 'Platform Bearer',
            'email' => 'platform-bearer@test.local',
            'password' => 'password',
        ]);

        $token = $platform->createToken('admin')->plainTextToken;

        Auth::forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Tenant', 'paroquia-teste')
            ->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Tenant', 'paroquia-teste')
            ->getJson('/api/v1/ecc/equipes')
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Tenant', 'paroquia-teste')
            ->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonFragment(['name' => 'admin-tenant']);
    }

    public function test_super_admin_bearer_can_assign_role_with_cold_tenancy(): void
    {
        $platform = SuperAdmin::query()->create([
            'name' => 'Platform Assign',
            'email' => 'platform-assign@test.local',
            'password' => 'password',
        ]);
        $token = $platform->createToken('admin')->plainTextToken;

        tenancy()->initialize($this->tenant);
        $target = User::findByEmail('admin@paroquia-teste.local');
        $this->assertNotNull($target);
        $igrejaId = Igreja::query()->firstOrFail()->id;
        $ulid = $target->ulid;
        tenancy()->end();
        Auth::forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Tenant', 'paroquia-teste')
            ->putJson("/api/v1/users/{$ulid}/roles", [
                'igreja_id' => $igrejaId,
                'roles' => [
                    ['name' => 'admin-tenant'],
                    ['name' => 'cadastros-usuarios'],
                ],
            ])
            ->assertOk()
            ->assertJsonFragment(['name' => 'cadastros-usuarios'])
            ->assertJsonFragment(['name' => 'admin-tenant']);
    }
}
