<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Igreja;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ViewAsLiderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ViewAsLiderEquipeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private string $igrejaId;

    private string $equipeAlphaId;

    private string $equipeBetaId;

    private string $casalAlphaId;

    private string $casalBetaId;

    private User $admin;

    private User $liderSemAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $platform = SuperAdmin::query()->create([
            'name' => 'Platform',
            'email' => 'platform-view-as@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($platform);

        $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Paróquia ViewAs',
            'slug' => 'paroquia-view-as',
        ])->assertCreated();

        $this->tenant = Tenant::query()->where('slug', 'paroquia-view-as')->firstOrFail();
        Auth::forgetGuards();

        tenancy()->initialize($this->tenant);
        $this->admin = User::findByEmail('admin@paroquia-view-as.local');
        $this->igrejaId = Igreja::query()->firstOrFail()->id;
        tenancy()->end();

        Sanctum::actingAs($this->admin);

        $this->equipeAlphaId = $this->tenantJson('POST', '/api/v1/ecc/equipes', [
            'nome' => 'Equipe Alpha',
            'cor' => '#111111',
        ])->assertCreated()->json('data.id');

        $this->equipeBetaId = $this->tenantJson('POST', '/api/v1/ecc/equipes', [
            'nome' => 'Equipe Beta',
            'cor' => '#222222',
        ])->assertCreated()->json('data.id');

        $this->casalAlphaId = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'equipe_id' => $this->equipeAlphaId,
            'nome' => 'João Alpha',
            'nome_conjuge' => 'Maria Alpha',
        ])->assertCreated()->json('data.id');

        $this->casalBetaId = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'equipe_id' => $this->equipeBetaId,
            'nome' => 'Pedro Beta',
            'nome_conjuge' => 'Ana Beta',
        ])->assertCreated()->json('data.id');

        $liderId = $this->tenantJson('POST', '/api/v1/users', [
            'name' => 'Só Líder',
            'email' => 'so-lider@paroquia-view-as.local',
            'password' => 'password123',
        ])->assertCreated()->json('data.id');

        $this->tenantJson('PUT', "/api/v1/users/{$liderId}/roles", [
            'igreja_id' => $this->igrejaId,
            'roles' => [
                [
                    'name' => 'lider-equipe',
                    'equipe_ids' => [$this->equipeAlphaId],
                ],
            ],
        ])->assertOk();

        tenancy()->initialize($this->tenant);
        $this->liderSemAdmin = User::findByEmail('so-lider@paroquia-view-as.local');
        tenancy()->end();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->deleteTenantDatabase($this->tenant->id);

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function tenantJson(string $method, string $uri, array $data = [])
    {
        return $this->withHeader('X-Tenant', 'paroquia-view-as')
            ->withHeader('X-Igreja', $this->igrejaId)
            ->json($method, $uri, $data);
    }

    public function test_nao_admin_nao_pode_ativar(): void
    {
        Auth::forgetGuards();
        Sanctum::actingAs($this->liderSemAdmin);

        $this->tenantJson('POST', '/api/v1/web/view-as/lider-equipe', [
            'equipe_ids' => [$this->equipeAlphaId],
        ])->assertForbidden();
    }

    public function test_equipe_ids_vazio_ou_invalido_retorna_422(): void
    {
        Sanctum::actingAs($this->admin);

        $this->tenantJson('POST', '/api/v1/web/view-as/lider-equipe', [
            'equipe_ids' => [],
        ])->assertStatus(422);

        $this->tenantJson('POST', '/api/v1/web/view-as/lider-equipe', [
            'equipe_ids' => ['01INVALIDEQUIPEID000000'],
        ])->assertStatus(422);
    }

    public function test_admin_ativa_mascara_me_e_escopo(): void
    {
        Sanctum::actingAs($this->admin);

        $me = $this->tenantJson('POST', '/api/v1/web/view-as/lider-equipe', [
            'equipe_ids' => [$this->equipeAlphaId],
        ])->assertOk()
            ->assertJsonPath('view_as.mode', 'lider-equipe')
            ->assertJsonPath('roles.0.name', 'lider-equipe')
            ->json();

        $this->assertSame([$this->equipeAlphaId], $me['view_as']['equipe_ids']);
        $this->assertContains('ecc.casais.atualizar', $me['permissions']);
        $this->assertContains('ecc.financeiro.depositos.submeter', $me['permissions']);
        $this->assertNotContains('ecc.casais.manage', $me['permissions']);
        $this->assertCount(1, $me['equipes_lideradas']);
        $this->assertSame($this->equipeAlphaId, $me['equipes_lideradas'][0]['id']);

        $this->tenantJson('POST', '/api/v1/web/me')
            ->assertOk()
            ->assertJsonPath('view_as.mode', 'lider-equipe')
            ->assertJsonPath('roles.0.name', 'lider-equipe');

        $equipes = $this->tenantJson('GET', '/api/v1/ecc/equipes')->assertOk()->json('data');
        $this->assertCount(1, $equipes);
        $this->assertSame($this->equipeAlphaId, $equipes[0]['id']);

        $casais = $this->tenantJson('GET', '/api/v1/ecc/casais')->assertOk()->json('data');
        $this->assertCount(1, $casais);
        $this->assertSame($this->casalAlphaId, $casais[0]['id']);
    }

    public function test_simulacao_permite_atualizar_ficha_e_bloqueia_manage(): void
    {
        Sanctum::actingAs($this->admin);

        $this->tenantJson('POST', '/api/v1/web/view-as/lider-equipe', [
            'equipe_ids' => [$this->equipeAlphaId],
        ])->assertOk();

        $this->tenantJson('PUT', '/api/v1/ecc/casais/'.$this->casalAlphaId, [
            'equipe_id' => $this->equipeBetaId,
            'nome' => 'João Alpha',
            'nome_conjuge' => 'Maria Alpha',
            'telefone' => '11999991111',
        ])->assertOk()
            ->assertJsonPath('data.telefone', '11999991111')
            ->assertJsonPath('data.equipe_id', $this->equipeAlphaId);

        $this->tenantJson('PUT', '/api/v1/ecc/casais/'.$this->casalBetaId, [
            'nome' => 'Pedro Beta',
            'nome_conjuge' => 'Ana Beta',
            'telefone' => '11888881111',
        ])->assertNotFound();

        $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'equipe_id' => $this->equipeAlphaId,
            'nome' => 'Novo',
            'nome_conjuge' => 'Nova',
        ])->assertForbidden();

        $this->tenantJson('DELETE', '/api/v1/ecc/casais/'.$this->casalAlphaId)
            ->assertForbidden();

        $this->tenantJson('POST', '/api/v1/ecc/casais/'.$this->casalAlphaId.'/swap')
            ->assertForbidden();
    }

    public function test_simulacao_permite_submeter_deposito_mensal(): void
    {
        Sanctum::actingAs($this->admin);

        $this->tenantJson('POST', '/api/v1/web/view-as/lider-equipe', [
            'equipe_ids' => [$this->equipeAlphaId],
        ])->assertOk();

        $this->withHeaders([
            'X-Tenant' => 'paroquia-view-as',
            'Accept' => 'application/json',
        ])->post('/api/v1/ecc/financeiro/depositos', [
            'equipe_id' => $this->equipeAlphaId,
            'competencia' => '2026-10',
            'valor' => '100.00',
            'comprovante' => UploadedFile::fake()->create('c.pdf', 20, 'application/pdf'),
        ])->assertCreated()
            ->assertJsonPath('data.status', 'provisionado');
    }

    public function test_delete_view_as_restaura_poderes(): void
    {
        Sanctum::actingAs($this->admin);

        $this->tenantJson('POST', '/api/v1/web/view-as/lider-equipe', [
            'equipe_ids' => [$this->equipeAlphaId],
        ])->assertOk();

        $this->tenantJson('DELETE', '/api/v1/web/view-as')
            ->assertOk()
            ->assertJsonPath('view_as', null)
            ->assertJsonPath('roles.0.name', 'admin-tenant');

        $casais = $this->tenantJson('GET', '/api/v1/ecc/casais')->assertOk()->json('data');
        $this->assertGreaterThanOrEqual(2, count($casais));

        $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'equipe_id' => $this->equipeAlphaId,
            'nome' => 'Admin Cria',
            'nome_conjuge' => 'Cônjuge',
        ])->assertCreated();
    }

    public function test_logout_limpa_simulacao(): void
    {
        Sanctum::actingAs($this->admin);

        $this->tenantJson('POST', '/api/v1/web/view-as/lider-equipe', [
            'equipe_ids' => [$this->equipeAlphaId],
        ])->assertOk();

        tenancy()->initialize($this->tenant);
        app(\App\Services\IgrejaContext::class)->set(Igreja::query()->findOrFail($this->igrejaId));
        $this->assertTrue(app(ViewAsLiderService::class)->isActive($this->admin));
        tenancy()->end();

        $this->tenantJson('POST', '/api/v1/web/logout')->assertOk();

        tenancy()->initialize($this->tenant);
        app(\App\Services\IgrejaContext::class)->set(Igreja::query()->findOrFail($this->igrejaId));
        $this->assertFalse(app(ViewAsLiderService::class)->isActive($this->admin));
        tenancy()->end();
    }

    public function test_troca_de_igreja_limpa_simulacao(): void
    {
        Sanctum::actingAs($this->admin);

        $outraIgrejaId = $this->tenantJson('POST', '/api/v1/igrejas', [
            'nome' => 'Capela Filial',
            'tipo' => 'comunidade',
        ])->assertCreated()->json('data.id');

        $this->tenantJson('POST', '/api/v1/web/view-as/lider-equipe', [
            'equipe_ids' => [$this->equipeAlphaId],
        ])->assertOk()->assertJsonPath('view_as.mode', 'lider-equipe');

        $this->withHeader('X-Tenant', 'paroquia-view-as')
            ->withHeader('X-Igreja', $outraIgrejaId)
            ->postJson('/api/v1/web/me')
            ->assertOk()
            ->assertJsonPath('view_as', null)
            ->assertJsonPath('roles.0.name', 'admin-tenant');

        // Estado foi limpo; voltar à igreja original também sem máscara
        $this->tenantJson('POST', '/api/v1/web/me')
            ->assertOk()
            ->assertJsonPath('view_as', null);
    }

    public function test_permissoes_lider_constantes_alinham_seed(): void
    {
        $this->assertSame([
            'telas.equipes',
            'telas.casais',
            'ecc.equipes.view',
            'ecc.casais.view',
            'ecc.casais.atualizar',
            'ecc.financeiro.depositos.submeter',
        ], ViewAsLiderService::LIDER_PERMISSIONS);
    }
}
