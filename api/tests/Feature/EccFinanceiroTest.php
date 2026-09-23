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

class EccFinanceiroTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private SuperAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = SuperAdmin::query()->create([
            'name' => 'Admin',
            'email' => 'admin-financeiro@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Demo Financeiro',
            'slug' => 'demo-financeiro',
        ])->assertCreated();

        $this->tenant = Tenant::query()->where('slug', 'demo-financeiro')->firstOrFail();
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
        return $this->withHeader('X-Tenant', 'demo-financeiro')->json($method, $uri, $data);
    }

    public function test_financeiro_requires_tenant_header(): void
    {
        $this->getJson('/api/v1/ecc/financeiro?ano=2026')->assertStatus(400);
    }

    public function test_financeiro_requires_auth(): void
    {
        Auth::forgetGuards();

        $this->tenantJson('GET', '/api/v1/ecc/financeiro?ano=2026')
            ->assertUnauthorized();
    }

    public function test_lider_nao_acessa_financeiro(): void
    {
        Auth::forgetGuards();

        tenancy()->initialize($this->tenant);
        $admin = User::findByEmail('admin@demo-financeiro.local');
        $igrejaId = Igreja::query()->firstOrFail()->id;
        tenancy()->end();

        Sanctum::actingAs($admin);

        $equipeId = $this->tenantJson('POST', '/api/v1/ecc/equipes', [
            'nome' => 'Equipe Fin',
            'cor' => '#111111',
        ])->assertCreated()->json('data.id');

        $liderId = $this->tenantJson('POST', '/api/v1/users', [
            'name' => 'Líder Sem Caixa',
            'email' => 'lider-fin@demo-financeiro.local',
            'password' => 'password123',
        ])->assertCreated()->json('data.id');

        $this->tenantJson('PUT', "/api/v1/users/{$liderId}/roles", [
            'igreja_id' => $igrejaId,
            'roles' => [
                [
                    'name' => 'lider-equipe',
                    'equipe_ids' => [$equipeId],
                ],
            ],
        ])->assertOk();

        tenancy()->initialize($this->tenant);
        $lider = User::findByEmail('lider-fin@demo-financeiro.local');
        tenancy()->end();

        Auth::forgetGuards();
        Sanctum::actingAs($lider);

        $this->tenantJson('GET', '/api/v1/ecc/financeiro?ano=2026')
            ->assertForbidden();
    }

    public function test_seed_contas_padrao_e_livro_vazio(): void
    {
        $contas = $this->tenantJson('GET', '/api/v1/ecc/financeiro/contas')
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $contas);
        $nomes = collect($contas)->pluck('nome')->all();
        $this->assertContains('Conta ECC (paróquia)', $nomes);
        $this->assertContains('Espécie / conta particular', $nomes);

        $livro = $this->tenantJson('GET', '/api/v1/ecc/financeiro?ano=2026')
            ->assertOk()
            ->json('data');

        $this->assertSame(2026, $livro['ano']);
        $this->assertCount(12, $livro['meses']);
        $this->assertEqualsWithDelta(0.0, $livro['saldo_final'], 0.01);
    }

    public function test_lancamento_entrada_saida_e_mes_negativo(): void
    {
        $contas = $this->tenantJson('GET', '/api/v1/ecc/financeiro/contas')
            ->assertOk()
            ->json('data');

        $banco = collect($contas)->firstWhere('tipo', 'banco');
        $this->assertNotNull($banco);

        $this->tenantJson('POST', '/api/v1/ecc/financeiro/lancamentos', [
            'conta_id' => $banco['id'],
            'data' => '2026-03-10',
            'historico' => 'Arrecadação março PIX',
            'tipo' => 'entrada',
            'valor' => 100,
        ])->assertCreated();

        $this->tenantJson('POST', '/api/v1/ecc/financeiro/lancamentos', [
            'conta_id' => $banco['id'],
            'data' => '2026-03-15',
            'historico' => 'Despesas da festiva',
            'tipo' => 'saida',
            'valor' => 250,
        ])->assertCreated();

        $livro = $this->tenantJson('GET', '/api/v1/ecc/financeiro?ano=2026')
            ->assertOk()
            ->json('data');

        $marco = collect($livro['meses'])->firstWhere('mes', 3);
        $this->assertNotNull($marco);
        $this->assertEqualsWithDelta(-150.0, $marco['total_mensal'], 0.01);
        $this->assertEqualsWithDelta(-150.0, $marco['acumulado'], 0.01);
        $this->assertEqualsWithDelta(-150.0, $livro['saldo_final'], 0.01);
    }

    public function test_transferencia_anula_no_total_mensal(): void
    {
        $contas = $this->tenantJson('GET', '/api/v1/ecc/financeiro/contas')
            ->assertOk()
            ->json('data');

        $banco = collect($contas)->firstWhere('tipo', 'banco');
        $especie = collect($contas)->firstWhere('tipo', 'especie');

        $this->tenantJson('POST', '/api/v1/ecc/financeiro/lancamentos', [
            'conta_id' => $especie['id'],
            'data' => '2026-04-01',
            'historico' => 'Saldo espécie',
            'tipo' => 'entrada',
            'valor' => 500,
        ])->assertCreated();

        $par = $this->tenantJson('POST', '/api/v1/ecc/financeiro/transferencias', [
            'conta_origem_id' => $especie['id'],
            'conta_destino_id' => $banco['id'],
            'data' => '2026-04-05',
            'historico' => 'Transferência bancária',
            'valor' => 300,
        ])->assertCreated()->json('data');

        $this->assertSame($par['saida']['transferencia_id'], $par['entrada']['transferencia_id']);
        $this->assertNotEmpty($par['saida']['transferencia_id']);

        $livro = $this->tenantJson('GET', '/api/v1/ecc/financeiro?ano=2026')
            ->assertOk()
            ->json('data');

        $abril = collect($livro['meses'])->firstWhere('mes', 4);
        // Entrada 500 na espécie + transferência (saída 300 / entrada 300) = total mensal 500
        $this->assertEqualsWithDelta(500.0, $abril['total_mensal'], 0.01);

        $this->tenantJson('POST', '/api/v1/ecc/financeiro/transferencias', [
            'conta_origem_id' => $banco['id'],
            'conta_destino_id' => $banco['id'],
            'data' => '2026-04-06',
            'historico' => 'Inválida',
            'valor' => 10,
        ])->assertStatus(422);

        $this->tenantJson('POST', '/api/v1/ecc/financeiro/lancamentos', [
            'conta_id' => $banco['id'],
            'data' => '2026-04-07',
            'historico' => '',
            'tipo' => 'entrada',
            'valor' => 0,
        ])->assertStatus(422);
    }

    public function test_excluir_transferencia_remove_par(): void
    {
        $contas = $this->tenantJson('GET', '/api/v1/ecc/financeiro/contas')
            ->assertOk()
            ->json('data');

        $banco = collect($contas)->firstWhere('tipo', 'banco');
        $especie = collect($contas)->firstWhere('tipo', 'especie');

        $par = $this->tenantJson('POST', '/api/v1/ecc/financeiro/transferencias', [
            'conta_origem_id' => $especie['id'],
            'conta_destino_id' => $banco['id'],
            'data' => '2026-05-01',
            'historico' => 'Transferência',
            'valor' => 80,
        ])->assertCreated()->json('data');

        $this->tenantJson('DELETE', '/api/v1/ecc/financeiro/lancamentos/'.$par['saida']['id'])
            ->assertNoContent();

        $livro = $this->tenantJson('GET', '/api/v1/ecc/financeiro?ano=2026')
            ->assertOk()
            ->json('data');

        $maio = collect($livro['meses'])->firstWhere('mes', 5);
        $this->assertCount(0, $maio['lancamentos']);
    }

    public function test_transportar_saldo_e_idempotente(): void
    {
        $contas = $this->tenantJson('GET', '/api/v1/ecc/financeiro/contas')
            ->assertOk()
            ->json('data');

        $banco = collect($contas)->firstWhere('tipo', 'banco');

        $this->tenantJson('POST', '/api/v1/ecc/financeiro/lancamentos', [
            'conta_id' => $banco['id'],
            'data' => '2025-11-20',
            'historico' => 'Arrecadação novembro',
            'tipo' => 'entrada',
            'valor' => 1200.50,
        ])->assertCreated();

        $aberturas = $this->tenantJson('POST', '/api/v1/ecc/financeiro/transportar', [
            'ano' => 2026,
        ])->assertCreated()->json('data');

        $this->assertNotEmpty($aberturas);
        $this->assertTrue($aberturas[0]['abertura']);
        $this->assertEqualsWithDelta(1200.50, $aberturas[0]['valor'], 0.01);
        $this->assertSame('2026-01-01', $aberturas[0]['data']);

        $livro = $this->tenantJson('GET', '/api/v1/ecc/financeiro?ano=2026')
            ->assertOk()
            ->json('data');

        $contaBanco = collect($livro['contas'])->firstWhere('id', $banco['id']);
        $this->assertEqualsWithDelta(1200.50, $contaBanco['saldo_abertura'], 0.01);
        $this->assertEqualsWithDelta(1200.50, $livro['saldo_final'], 0.01);

        $this->tenantJson('POST', '/api/v1/ecc/financeiro/transportar', [
            'ano' => 2026,
        ])->assertStatus(422);
    }

    public function test_nao_exclui_conta_com_lancamento(): void
    {
        $contas = $this->tenantJson('GET', '/api/v1/ecc/financeiro/contas')
            ->assertOk()
            ->json('data');

        $banco = collect($contas)->firstWhere('tipo', 'banco');

        $this->tenantJson('POST', '/api/v1/ecc/financeiro/lancamentos', [
            'conta_id' => $banco['id'],
            'data' => '2026-01-10',
            'historico' => 'Entrada',
            'tipo' => 'entrada',
            'valor' => 10,
        ])->assertCreated();

        $this->tenantJson('DELETE', '/api/v1/ecc/financeiro/contas/'.$banco['id'])
            ->assertStatus(422);

        $this->tenantJson('PUT', '/api/v1/ecc/financeiro/contas/'.$banco['id'], [
            'ativa' => false,
        ])->assertOk()
            ->assertJsonPath('data.ativa', false);
    }

    public function test_criar_conta_caixinha(): void
    {
        $this->tenantJson('GET', '/api/v1/ecc/financeiro/contas')->assertOk();

        $caixinha = $this->tenantJson('POST', '/api/v1/ecc/financeiro/contas', [
            'nome' => 'Caixinha',
            'tipo' => 'especie',
        ])->assertCreated()->json('data');

        $this->assertSame('Caixinha', $caixinha['nome']);
        $this->assertSame('especie', $caixinha['tipo']);
    }
}
