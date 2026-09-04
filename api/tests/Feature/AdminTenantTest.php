<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SuperAdmin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTenantTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): SuperAdmin
    {
        return SuperAdmin::query()->create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => 'password',
        ]);
    }

    public function test_admin_login_returns_token(): void
    {
        $this->admin();

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@test.local',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['id', 'name', 'email', 'access_token']);
    }

    public function test_admin_login_rejects_invalid_credentials(): void
    {
        $this->admin();

        $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@test.local',
            'password' => 'wrong',
        ])->assertForbidden();
    }

    public function test_admin_me_works_with_database_session_and_ulid(): void
    {
        config(['session.driver' => 'database']);

        $admin = $this->admin();
        $token = $admin->createToken('admin')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('Origin', 'http://localhost:5173')
            ->withHeader('Referer', 'http://localhost:5173/')
            ->postJson('/api/v1/admin/me')
            ->assertOk()
            ->assertJsonPath('id', $admin->id)
            ->assertJsonPath('email', $admin->email);
    }

    public function test_tenants_require_authentication(): void
    {
        $this->getJson('/api/v1/admin/tenants')->assertUnauthorized();
    }

    public function test_can_create_and_list_tenants(): void
    {
        Sanctum::actingAs($this->admin());

        $create = $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Paróquia Centro',
            'slug' => 'paroquia-centro',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Paróquia Centro')
            ->assertJsonPath('data.slug', 'paroquia-centro');

        $tenantId = $create->json('data.id');
        $this->assertNotEmpty($tenantId);

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->getJson('/api/v1/admin/tenants')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'paroquia-centro');

        // limpa arquivo sqlite do tenant se existir
        $dbPath = database_path('tenant'.$tenantId);
        if (File::exists($dbPath)) {
            File::delete($dbPath);
        }
    }

    public function test_tenant_slug_must_be_unique(): void
    {
        Sanctum::actingAs($this->admin());

        Tenant::withoutEvents(function () {
            Tenant::create([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'name' => 'A',
                'slug' => 'mesmo-slug',
            ]);
        });

        $this->postJson('/api/v1/admin/tenants', [
            'name' => 'B',
            'slug' => 'mesmo-slug',
        ])->assertUnprocessable();
    }
}
