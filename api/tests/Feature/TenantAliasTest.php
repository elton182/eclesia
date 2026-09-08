<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\TenantAlias;
use App\Services\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantAliasTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): SuperAdmin
    {
        return SuperAdmin::query()->create([
            'name' => 'Admin',
            'email' => 'admin-alias@test.local',
            'password' => 'password',
        ]);
    }

    public function test_create_tenant_with_aliases(): void
    {
        Sanctum::actingAs($this->admin());

        $create = $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Paróquia São José',
            'slug' => 'sao-jose',
            'aliases' => ['SÃO JOSÉ', 'psj'],
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.slug', 'sao-jose');

        $aliases = $create->json('data.aliases');
        $this->assertContains('psj', $aliases);
        $this->assertContains('são josé', $aliases);

        $tenantId = $create->json('data.id');
        $this->cleanupTenantDb($tenantId);
    }

    public function test_resolver_finds_by_slug_alias_and_name(): void
    {
        $tenant = Tenant::withoutEvents(fn () => Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Comunidade Luz',
            'slug' => 'comunidade-luz',
        ]));

        TenantAlias::query()->create([
            'tenant_id' => $tenant->id,
            'alias' => 'luz',
        ]);

        $resolver = app(TenantResolver::class);

        $this->assertSame($tenant->id, $resolver->resolve('comunidade-luz')->id);
        $this->assertSame($tenant->id, $resolver->resolve('LUZ')->id);
        $this->assertSame($tenant->id, $resolver->resolve('Comunidade Luz')->id);
    }

    public function test_resolver_rejects_ambiguous_name(): void
    {
        Tenant::withoutEvents(function () {
            Tenant::create([
                'id' => (string) Str::uuid(),
                'name' => 'Mesmo Nome',
                'slug' => 'mesmo-a',
            ]);
            Tenant::create([
                'id' => (string) Str::uuid(),
                'name' => 'Mesmo Nome',
                'slug' => 'mesmo-b',
            ]);
        });

        $this->expectException(\App\Exceptions\TenantResolutionException::class);
        app(TenantResolver::class)->resolve('Mesmo Nome');
    }

    public function test_update_aliases(): void
    {
        Sanctum::actingAs($this->admin());

        $create = $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Tenant Alias Update',
            'slug' => 'alias-update',
            'aliases' => ['old'],
        ]);
        $create->assertCreated();
        $id = $create->json('data.id');

        $this->putJson("/api/v1/admin/tenants/{$id}", [
            'aliases' => ['novo-apelido'],
        ])->assertOk()
            ->assertJsonPath('data.aliases.0', 'novo-apelido');

        $this->cleanupTenantDb($id);
    }

    private function cleanupTenantDb(string $tenantId): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        $dbPath = database_path('tenant'.$tenantId);
        if (File::exists($dbPath)) {
            File::delete($dbPath);
        }
    }
}
