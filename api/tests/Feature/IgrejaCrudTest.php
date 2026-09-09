<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EccEquipe;
use App\Models\Igreja;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IgrejaCrudTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private SuperAdmin $platform;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = SuperAdmin::query()->create([
            'name' => 'Admin',
            'email' => 'platform@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($this->platform);

        $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Org Teste',
            'slug' => 'org-teste',
        ])->assertCreated();

        $this->tenant = Tenant::query()->where('slug', 'org-teste')->firstOrFail();
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function asAdminTenant(string $method, string $uri, array $data = [], array $headers = [])
    {
        tenancy()->initialize($this->tenant);
        $admin = User::findByEmail('admin@org-teste.local');
        tenancy()->end();

        Sanctum::actingAs($admin);

        $req = $this->withHeader('X-Tenant', 'org-teste');
        foreach ($headers as $key => $value) {
            $req = $req->withHeader($key, $value);
        }

        return $req->json($method, $uri, $data);
    }

    public function test_admin_tenant_can_crud_igrejas(): void
    {
        $created = $this->asAdminTenant('POST', '/api/v1/igrejas', [
            'nome' => 'Comunidade Norte',
            'tipo' => 'comunidade',
            'cidade' => 'Campinas',
            'uf' => 'sp',
            'email' => 'norte@example.com',
            'telefone' => '11999990000',
        ])->assertCreated()
            ->assertJsonPath('data.nome', 'Comunidade Norte')
            ->assertJsonPath('data.tipo', 'comunidade')
            ->assertJsonPath('data.uf', 'SP')
            ->json('data');

        $this->asAdminTenant('GET', '/api/v1/igrejas')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->asAdminTenant('PUT', '/api/v1/igrejas/'.$created['id'], [
            'nome' => 'Comunidade Norte Atualizada',
            'tipo' => 'comunidade',
            'bairro' => 'Centro',
        ])->assertOk()
            ->assertJsonPath('data.nome', 'Comunidade Norte Atualizada')
            ->assertJsonPath('data.bairro', 'Centro');

        $this->asAdminTenant('DELETE', '/api/v1/igrejas/'.$created['id'])
            ->assertNoContent();

        $this->asAdminTenant('GET', '/api/v1/igrejas')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_delete_blocked_when_has_equipe(): void
    {
        tenancy()->initialize($this->tenant);
        $igreja = Igreja::query()->firstOrFail();
        EccEquipe::query()->create([
            'igreja_id' => $igreja->id,
            'nome' => 'EQUIPE A',
            'cor' => '#000',
        ]);
        Igreja::query()->create([
            'nome' => 'Outra',
            'tipo' => 'outro',
        ]);
        tenancy()->end();

        $this->asAdminTenant('DELETE', '/api/v1/igrejas/'.$igreja->id)
            ->assertStatus(409);
    }

    public function test_admin_igreja_can_update_own_but_not_create_or_delete(): void
    {
        tenancy()->initialize($this->tenant);
        $igrejaA = Igreja::query()->firstOrFail();
        $igrejaB = Igreja::query()->create([
            'nome' => 'Segunda Igreja',
            'tipo' => 'paroquia',
        ]);

        $user = User::query()->create([
            'name' => 'Admin Igreja',
            'email' => 'admin-igreja@org-teste.local',
            'password' => 'password',
            'is_active' => true,
        ]);
        setPermissionsTeamId($igrejaA->id);
        $user->assignRole('admin-igreja');
        setPermissionsTeamId(null);
        tenancy()->end();

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant', 'org-teste')
            ->postJson('/api/v1/igrejas', [
                'nome' => 'Nova',
                'tipo' => 'paroquia',
            ])
            ->assertForbidden();

        $this->withHeader('X-Tenant', 'org-teste')
            ->putJson('/api/v1/igrejas/'.$igrejaA->id, [
                'nome' => 'Paróquia Editada',
                'tipo' => 'paroquia',
                'cidade' => 'Santos',
            ])
            ->assertOk()
            ->assertJsonPath('data.nome', 'Paróquia Editada');

        $this->withHeader('X-Tenant', 'org-teste')
            ->putJson('/api/v1/igrejas/'.$igrejaB->id, [
                'nome' => 'Hack',
                'tipo' => 'paroquia',
            ])
            ->assertForbidden();

        $this->withHeader('X-Tenant', 'org-teste')
            ->deleteJson('/api/v1/igrejas/'.$igrejaB->id)
            ->assertForbidden();

        $list = $this->withHeader('X-Tenant', 'org-teste')
            ->getJson('/api/v1/igrejas')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $list);
        $this->assertSame($igrejaA->id, $list[0]['id']);
    }

    public function test_x_igreja_header_scopes_equipes(): void
    {
        tenancy()->initialize($this->tenant);
        $igrejaA = Igreja::query()->firstOrFail();
        $igrejaB = Igreja::query()->create([
            'nome' => 'Igreja B',
            'tipo' => 'comunidade',
        ]);
        EccEquipe::query()->create([
            'igreja_id' => $igrejaA->id,
            'nome' => 'EQUIPE A',
            'cor' => '#111',
        ]);
        EccEquipe::query()->create([
            'igreja_id' => $igrejaB->id,
            'nome' => 'EQUIPE B',
            'cor' => '#222',
        ]);
        tenancy()->end();

        $listA = $this->asAdminTenant('GET', '/api/v1/ecc/equipes', [], [
            'X-Igreja' => $igrejaA->id,
        ])->assertOk()->json('data');

        $listB = $this->asAdminTenant('GET', '/api/v1/ecc/equipes', [], [
            'X-Igreja' => $igrejaB->id,
        ])->assertOk()->json('data');

        $this->assertCount(1, $listA);
        $this->assertSame('EQUIPE A', $listA[0]['nome']);
        $this->assertCount(1, $listB);
        $this->assertSame('EQUIPE B', $listB[0]['nome']);
    }

    public function test_x_igreja_forbidden_when_no_access(): void
    {
        tenancy()->initialize($this->tenant);
        $igrejaA = Igreja::query()->firstOrFail();
        $igrejaB = Igreja::query()->create([
            'nome' => 'Outra',
            'tipo' => 'outro',
        ]);

        $user = User::query()->create([
            'name' => 'Cad Equipes',
            'email' => 'cad@org-teste.local',
            'password' => 'password',
            'is_active' => true,
        ]);
        setPermissionsTeamId($igrejaA->id);
        $user->assignRole('cadastros-equipes');
        setPermissionsTeamId(null);
        tenancy()->end();

        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant', 'org-teste')
            ->withHeader('X-Igreja', $igrejaB->id)
            ->getJson('/api/v1/ecc/equipes')
            ->assertForbidden();
    }

    public function test_validation_rejects_invalid_tipo(): void
    {
        $this->asAdminTenant('POST', '/api/v1/igrejas', [
            'nome' => 'X',
            'tipo' => 'capela',
        ])->assertStatus(422);
    }
}
