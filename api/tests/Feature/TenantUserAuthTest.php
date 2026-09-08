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
                'name' => 'Secretária',
                'email' => 'sec@paroquia-teste.local',
                'password' => 'password123',
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.email', 'sec@paroquia-teste.local');

        $userId = $create->json('data.id');

        $this->withHeader('X-Tenant', 'paroquia-teste')
            ->postJson("/api/v1/users/{$userId}/roles", [
                'role' => 'secretaria',
                'igreja_id' => $igrejaId,
            ])
            ->assertOk()
            ->assertJsonFragment(['name' => 'secretaria']);

        $this->withHeader('X-Tenant', 'paroquia-teste')
            ->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonFragment(['name' => 'admin-tenant']);

        $this->withHeader('X-Tenant', 'paroquia-teste')
            ->getJson('/api/v1/permissions')
            ->assertOk()
            ->assertJsonFragment(['name' => 'users.view']);

        $this->withHeader('X-Tenant', 'paroquia-teste')
            ->getJson('/api/v1/users')
            ->assertOk();
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
            ->assertOk();
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
            ->postJson("/api/v1/users/{$ulid}/roles", [
                'role' => 'secretaria',
                'igreja_id' => $igrejaId,
            ])
            ->assertOk()
            ->assertJsonFragment(['name' => 'secretaria']);
    }
}
