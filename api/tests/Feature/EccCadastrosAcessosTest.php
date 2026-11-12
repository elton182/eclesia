<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Igreja;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EccCadastrosAcessosTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private string $igrejaId;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $platform = SuperAdmin::query()->create([
            'name' => 'Platform',
            'email' => 'platform-cadastros-acessos@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($platform);

        $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Paróquia Relatórios',
            'slug' => 'paroquia-relatorios',
        ])->assertCreated();

        $this->tenant = Tenant::query()->where('slug', 'paroquia-relatorios')->firstOrFail();
        Auth::forgetGuards();

        tenancy()->initialize($this->tenant);
        $this->admin = User::findByEmail('admin@paroquia-relatorios.local');
        $this->igrejaId = Igreja::query()->firstOrFail()->id;
        tenancy()->end();

        Sanctum::actingAs($this->admin);
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
        return $this->withHeader('X-Tenant', 'paroquia-relatorios')->json($method, $uri, $data);
    }

    public function test_relatorio_requer_tenant_e_auth(): void
    {
        $this->getJson('/api/v1/ecc/relatorios/cadastros-acessos')->assertStatus(400);

        Auth::forgetGuards();

        $this->tenantJson('GET', '/api/v1/ecc/relatorios/cadastros-acessos')
            ->assertUnauthorized();
    }

    public function test_lider_nao_acessa_relatorio(): void
    {
        $equipeId = $this->tenantJson('POST', '/api/v1/ecc/equipes', [
            'nome' => 'Equipe Rel',
            'cor' => '#222222',
        ])->assertCreated()->json('data.id');

        $liderId = $this->tenantJson('POST', '/api/v1/users', [
            'name' => 'Líder Rel',
            'email' => 'lider-rel@paroquia-relatorios.local',
            'password' => 'password123',
        ])->assertCreated()->json('data.id');

        $this->tenantJson('PUT', "/api/v1/users/{$liderId}/roles", [
            'igreja_id' => $this->igrejaId,
            'roles' => [
                [
                    'name' => 'lider-equipe',
                    'equipe_ids' => [$equipeId],
                ],
            ],
        ])->assertOk();

        tenancy()->initialize($this->tenant);
        $lider = User::findByEmail('lider-rel@paroquia-relatorios.local');
        tenancy()->end();

        Auth::forgetGuards();
        Sanctum::actingAs($lider);

        $this->tenantJson('GET', '/api/v1/ecc/relatorios/cadastros-acessos')
            ->assertForbidden();
    }

    public function test_janela_dias_invalida_retorna_422(): void
    {
        $this->tenantJson('GET', '/api/v1/ecc/relatorios/cadastros-acessos?janela_dias=2')
            ->assertStatus(422);
    }

    public function test_admin_ve_resumo_equipes_e_atualizacoes(): void
    {
        $equipeAtivaId = $this->tenantJson('POST', '/api/v1/ecc/equipes', [
            'nome' => 'Equipe Ativa',
            'cor' => '#111111',
        ])->assertCreated()->json('data.id');

        $equipeSemAcessoId = $this->tenantJson('POST', '/api/v1/ecc/equipes', [
            'nome' => 'Equipe Sem Acesso',
            'cor' => '#333333',
        ])->assertCreated()->json('data.id');

        $acesso = $this->tenantJson('POST', "/api/v1/ecc/equipes/{$equipeAtivaId}/acesso")
            ->assertCreated()
            ->json('data');

        $this->assertNotEmpty($acesso['username'] ?? null);

        $casalId = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'equipe_id' => $equipeAtivaId,
            'nome' => 'José',
            'nome_conjuge' => 'Ana',
            'sexo' => 'M',
            'sexo_conjuge' => 'F',
        ])->assertCreated()->json('data.id');

        $this->tenantJson('PUT', "/api/v1/ecc/casais/{$casalId}", [
            'nome' => 'José',
            'nome_conjuge' => 'Ana',
            'observacoes' => 'Atualizado no teste',
        ])->assertOk();

        // Login do acesso da equipe → last_login_at + status ativo
        Auth::forgetGuards();
        $this->postJson('/api/v1/web/login', [
            'tenant' => 'paroquia-relatorios',
            'login' => $acesso['username'],
            'password' => $acesso['password'] ?? '1234',
        ])->assertOk();

        // Troca senha obrigatória para poder autenticar de novo como admin
        tenancy()->initialize($this->tenant);
        $acessoUser = User::query()->where('username', $acesso['username'])->firstOrFail();
        $acessoUser->forceFill([
            'must_change_password' => false,
            'password' => 'password123',
        ])->saveQuietly();
        tenancy()->end();

        Sanctum::actingAs($this->admin);

        $response = $this->tenantJson('GET', '/api/v1/ecc/relatorios/cadastros-acessos?janela_dias=30')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'resumo' => [
                        'janela_dias',
                        'casais_total',
                        'casais_atualizados_janela',
                        'casais_estagnados',
                        'equipes_total',
                        'equipes_com_acesso',
                        'equipes_sem_acesso',
                        'equipes_nunca_acessaram',
                        'equipes_inativas_janela',
                    ],
                    'equipes_acessos',
                    'quem_atualizou',
                    'atualizacoes_recentes',
                    'casais_estagnados',
                ],
            ]);

        $data = $response->json('data');
        $this->assertSame(30, $data['resumo']['janela_dias']);
        $this->assertGreaterThanOrEqual(1, $data['resumo']['casais_total']);
        $this->assertGreaterThanOrEqual(1, $data['resumo']['casais_atualizados_janela']);
        $this->assertGreaterThanOrEqual(2, $data['resumo']['equipes_total']);
        $this->assertGreaterThanOrEqual(1, $data['resumo']['equipes_com_acesso']);
        $this->assertGreaterThanOrEqual(1, $data['resumo']['equipes_sem_acesso']);

        $porNome = collect($data['equipes_acessos'])->keyBy(
            static fn (array $row): string => mb_strtoupper((string) $row['nome'])
        );
        $this->assertSame('ativo', $porNome['EQUIPE ATIVA']['status_acesso']);
        $this->assertNotNull($porNome['EQUIPE ATIVA']['ultimo_acesso_em']);
        $this->assertSame('sem_acesso', $porNome['EQUIPE SEM ACESSO']['status_acesso']);
        $this->assertSame($equipeSemAcessoId, $porNome['EQUIPE SEM ACESSO']['id']);

        $this->assertNotEmpty($data['atualizacoes_recentes']);
        $atualizacaoCasal = collect($data['atualizacoes_recentes'])
            ->first(static fn (array $row): bool => ($row['casal_id'] ?? null) === $casalId);
        $this->assertNotNull($atualizacaoCasal);
        $this->assertStringContainsString('José', (string) $atualizacaoCasal['casal_label']);
        $this->assertStringContainsString('Ana', (string) $atualizacaoCasal['casal_label']);
        $this->assertDoesNotMatchRegularExpression(
            '/^[0-9A-HJKMNP-TV-Z]{26}$/i',
            (string) $atualizacaoCasal['casal_label'],
        );

        $this->assertNotEmpty($data['quem_atualizou']);
    }

    public function test_relatorio_resolve_label_quando_auditoria_tem_ulid(): void
    {
        $casalId = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'nome' => 'Pedro',
            'nome_conjuge' => 'Lucia',
            'sexo' => 'M',
            'sexo_conjuge' => 'F',
        ])->assertCreated()->json('data.id');

        tenancy()->initialize($this->tenant);
        \App\Models\AuditLog::query()
            ->where('auditable_type', \App\Models\Casal::class)
            ->where('auditable_ulid', $casalId)
            ->update(['auditable_label' => $casalId]);
        tenancy()->end();

        $response = $this->tenantJson('GET', '/api/v1/ecc/relatorios/cadastros-acessos')
            ->assertOk();

        $row = collect($response->json('data.atualizacoes_recentes'))
            ->first(static fn (array $item): bool => ($item['casal_id'] ?? null) === $casalId);

        $this->assertNotNull($row);
        $this->assertStringContainsString('Pedro', (string) $row['casal_label']);
        $this->assertStringContainsString('Lucia', (string) $row['casal_label']);
    }

    public function test_login_grava_last_login_at(): void
    {
        Auth::forgetGuards();

        $this->postJson('/api/v1/web/login', [
            'tenant' => 'paroquia-relatorios',
            'login' => 'admin@paroquia-relatorios.local',
            'password' => 'password',
        ])->assertOk();

        tenancy()->initialize($this->tenant);
        $admin = User::findByEmail('admin@paroquia-relatorios.local');
        $this->assertNotNull($admin?->last_login_at);
        tenancy()->end();
    }

    public function test_equipe_com_acesso_sem_login_fica_nunca_acessou(): void
    {
        $equipeId = $this->tenantJson('POST', '/api/v1/ecc/equipes', [
            'nome' => 'Equipe Nova',
        ])->assertCreated()->json('data.id');

        $this->tenantJson('POST', "/api/v1/ecc/equipes/{$equipeId}/acesso")
            ->assertCreated();

        $response = $this->tenantJson('GET', '/api/v1/ecc/relatorios/cadastros-acessos')
            ->assertOk();

        $row = collect($response->json('data.equipes_acessos'))
            ->first(static fn (array $eq): bool => mb_strtoupper((string) $eq['nome']) === 'EQUIPE NOVA');

        $this->assertNotNull($row);
        $this->assertTrue($row['tem_acesso']);
        $this->assertSame('nunca_acessou', $row['status_acesso']);
        $this->assertNull($row['ultimo_acesso_em']);
    }
}
