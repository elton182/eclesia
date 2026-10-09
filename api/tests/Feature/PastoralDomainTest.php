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

class PastoralDomainTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private SuperAdmin $admin;

    private string $igrejaId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = SuperAdmin::query()->create([
            'name' => 'Admin',
            'email' => 'admin-pastoral@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Demo Pastorais',
            'slug' => 'demo-pastorais',
        ]);

        $response->assertCreated();
        $this->tenant = Tenant::query()->where('slug', 'demo-pastorais')->firstOrFail();

        tenancy()->initialize($this->tenant);
        $this->igrejaId = Igreja::query()->firstOrFail()->id;
        tenancy()->end();
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
    private function tenantJson(string $method, string $uri, array $data = [])
    {
        return $this->withHeaders([
            'X-Tenant' => 'demo-pastorais',
            'X-Igreja' => $this->igrejaId,
        ])->json($method, $uri, $data);
    }

    private function actingAsRole(string $role, string $email = 'user-pastoral@test.local'): User
    {
        tenancy()->initialize($this->tenant);
        $user = User::query()->create([
            'name' => 'User '.$role,
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);
        setPermissionsTeamId($this->igrejaId);
        $user->assignRole($role);
        setPermissionsTeamId(null);
        tenancy()->end();

        Auth::forgetGuards();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_nao_autenticado_retorna_401(): void
    {
        Auth::forgetGuards();
        $this->app['auth']->forgetGuards();

        $this->withHeaders([
            'X-Tenant' => 'demo-pastorais',
            'X-Igreja' => $this->igrejaId,
        ])->getJson('/api/v1/pastorais')->assertUnauthorized();
    }

    public function test_sem_permissao_retorna_403(): void
    {
        $this->actingAsRole('lider-equipe', 'lider-sem-pastoral@test.local');

        $this->tenantJson('GET', '/api/v1/pastorais')->assertForbidden();
    }

    public function test_crud_pastorais_e_membros(): void
    {
        Sanctum::actingAs($this->admin);

        $created = $this->tenantJson('POST', '/api/v1/pastorais', [
            'nome' => 'Pastoral da Família',
            'ordem' => 1,
            'ativa' => true,
        ])->assertCreated()
            ->assertJsonPath('data.nome', 'Pastoral da Família')
            ->assertJsonPath('data.ativa', true)
            ->json('data');

        $this->assertNotEmpty($created['id']);

        $this->tenantJson('GET', '/api/v1/pastorais')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->tenantJson('PATCH', '/api/v1/pastorais/'.$created['id'], [
            'nome' => 'Pastoral Familiar',
        ])->assertOk()
            ->assertJsonPath('data.nome', 'Pastoral Familiar');

        tenancy()->initialize($this->tenant);
        $membro = User::query()->create([
            'name' => 'Coord Família',
            'email' => 'coord-familia@test.local',
            'password' => 'password',
            'is_active' => true,
        ]);
        $membroUlid = $membro->ulid;
        tenancy()->end();

        Sanctum::actingAs($this->admin);

        $this->tenantJson('POST', '/api/v1/pastorais/'.$created['id'].'/membros', [
            'user_id' => $membroUlid,
            'papel' => 'coordenador',
        ])->assertCreated()
            ->assertJsonPath('data.user_id', $membroUlid)
            ->assertJsonPath('data.papel', 'coordenador');

        $this->tenantJson('POST', '/api/v1/pastorais/'.$created['id'].'/membros', [
            'user_id' => $membroUlid,
            'papel' => 'membro',
        ])->assertStatus(422);

        $this->tenantJson('GET', '/api/v1/pastorais/'.$created['id'].'/membros')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->tenantJson('DELETE', '/api/v1/pastorais/'.$created['id'].'/membros/'.$membroUlid)
            ->assertNoContent();

        $this->tenantJson('GET', '/api/v1/pastorais/'.$created['id'].'/membros')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->tenantJson('DELETE', '/api/v1/pastorais/'.$created['id'])
            ->assertNoContent();
    }

    public function test_validacao_membro_422(): void
    {
        Sanctum::actingAs($this->admin);

        $pastoral = $this->tenantJson('POST', '/api/v1/pastorais', [
            'nome' => 'Liturgia',
        ])->assertCreated()->json('data');

        $this->tenantJson('POST', '/api/v1/pastorais/'.$pastoral['id'].'/membros', [
            'user_id' => 'invalid',
            'papel' => 'coordenador',
        ])->assertStatus(422);

        $this->tenantJson('POST', '/api/v1/pastorais/'.$pastoral['id'].'/membros', [
            'user_id' => '01HZZZZZZZZZZZZZZZZZZZZZZZ',
            'papel' => 'chefe',
        ])->assertStatus(422);
    }

    public function test_site_pastorais_continua_funcionando(): void
    {
        Sanctum::actingAs($this->admin);

        $this->tenantJson('POST', '/api/v1/site/pastorais', [
            'igreja_id' => $this->igrejaId,
            'nome' => 'Site Pastoral',
            'publicado_no_site' => true,
        ])->assertCreated()
            ->assertJsonPath('data.nome', 'Site Pastoral');

        $this->tenantJson('GET', '/api/v1/site/pastorais')
            ->assertOk();
    }

    public function test_coordenador_pode_ver_mas_nao_gerir(): void
    {
        Sanctum::actingAs($this->admin);
        $pastoral = $this->tenantJson('POST', '/api/v1/pastorais', [
            'nome' => 'Jovens',
        ])->assertCreated()->json('data');

        $coord = $this->actingAsRole('coordenador-pastoral', 'coord-view@test.local');

        tenancy()->initialize($this->tenant);
        \App\Models\Pastoral::query()->findOrFail($pastoral['id'])
            ->membros()->attach($coord->id, ['papel' => 'coordenador']);
        tenancy()->end();

        Sanctum::actingAs($coord);

        $this->tenantJson('GET', '/api/v1/pastorais')->assertOk();
        $this->tenantJson('POST', '/api/v1/pastorais', [
            'nome' => 'Hack',
        ])->assertForbidden();
        $this->tenantJson('PATCH', '/api/v1/pastorais/'.$pastoral['id'], [
            'nome' => 'Hack 2',
        ])->assertForbidden();
        $this->tenantJson('DELETE', '/api/v1/pastorais/'.$pastoral['id'])
            ->assertForbidden();

        $me = $this->tenantJson('POST', '/api/v1/web/me')->assertOk()->json();
        $this->assertTrue(
            collect($me['pastorais'] ?? [])->contains(fn ($p) => ($p['id'] ?? null) === $pastoral['id']),
            'web/me deve incluir pastorais vinculadas ao usuário'
        );
    }
}
