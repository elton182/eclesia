<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Igreja;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EccFinanceiroDepositoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private string $igrejaId;

    private string $equipeAlphaId;

    private string $equipeBetaId;

    private User $admin;

    private User $lider;

    protected function setUp(): void
    {
        parent::setUp();

        $platform = SuperAdmin::query()->create([
            'name' => 'Platform',
            'email' => 'platform-deposito@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($platform);

        $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Paróquia Depósito',
            'slug' => 'paroquia-deposito',
        ])->assertCreated();

        $this->tenant = Tenant::query()->where('slug', 'paroquia-deposito')->firstOrFail();
        Auth::forgetGuards();

        tenancy()->initialize($this->tenant);
        $this->admin = User::findByEmail('admin@paroquia-deposito.local');
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

        $liderId = $this->tenantJson('POST', '/api/v1/users', [
            'name' => 'Líder Alpha',
            'email' => 'lider-deposito@paroquia-deposito.local',
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
        $this->lider = User::findByEmail('lider-deposito@paroquia-deposito.local');
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
        return $this->withHeader('X-Tenant', 'paroquia-deposito')->json($method, $uri, $data);
    }

    private function asLider(): void
    {
        Auth::forgetGuards();
        Sanctum::actingAs($this->lider);
    }

    private function asAdmin(): void
    {
        Auth::forgetGuards();
        Sanctum::actingAs($this->admin);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function postDeposito(array $extra = [])
    {
        $payload = array_merge([
            'equipe_id' => $this->equipeAlphaId,
            'competencia' => '2026-10',
            'valor' => '150.50',
            'comprovante' => UploadedFile::fake()->create('comprovante.pdf', 100, 'application/pdf'),
        ], $extra);

        return $this->withHeaders([
            'X-Tenant' => 'paroquia-deposito',
            'Accept' => 'application/json',
        ])->post('/api/v1/ecc/financeiro/depositos', $payload);
    }

    public function test_deposito_requer_auth(): void
    {
        Auth::forgetGuards();

        $this->withHeader('X-Tenant', 'paroquia-deposito')
            ->post('/api/v1/ecc/financeiro/depositos', [
                'equipe_id' => $this->equipeAlphaId,
                'competencia' => '2026-10',
                'valor' => '100',
                'comprovante' => UploadedFile::fake()->create('c.pdf', 10, 'application/pdf'),
            ])
            ->assertUnauthorized();
    }

    public function test_lider_submete_deposito_provisionado(): void
    {
        $this->asLider();

        $data = $this->postDeposito()
            ->assertCreated()
            ->json('data');

        $this->assertSame('provisionado', $data['status']);
        $this->assertSame('2026-10', $data['competencia']);
        $this->assertSame(150.5, $data['valor']);
        $this->assertSame($this->equipeAlphaId, $data['equipe_id']);
        $this->assertNotEmpty($data['lancamento_id']);
        $this->assertStringContainsString('/comprovante', $data['comprovante_url']);

        $this->asAdmin();
        $livro = $this->tenantJson('GET', '/api/v1/ecc/financeiro?ano=2026')
            ->assertOk()
            ->json('data');

        $this->assertEquals(0, $livro['saldo_final']);

        $outubro = collect($livro['meses'])->firstWhere('mes', 10);
        $this->assertNotNull($outubro);
        $provisionados = collect($outubro['lancamentos'])->where('status', 'provisionado');
        $this->assertCount(1, $provisionados);
        $this->assertEquals(0, $outubro['total_mensal']);
    }

    public function test_lider_nao_submete_equipe_alheia(): void
    {
        $this->asLider();

        $this->postDeposito(['equipe_id' => $this->equipeBetaId])
            ->assertNotFound();
    }

    public function test_competencia_duplicada_ativa_retorna_422(): void
    {
        $this->asLider();
        $this->postDeposito()->assertCreated();
        $this->postDeposito()->assertStatus(422);
    }

    public function test_aprovar_confirma_no_livro(): void
    {
        $this->asLider();
        $id = $this->postDeposito()->assertCreated()->json('data.id');

        $this->asAdmin();
        $aprovado = $this->tenantJson('POST', "/api/v1/ecc/financeiro/depositos/{$id}/aprovar")
            ->assertOk()
            ->json('data');

        $this->assertSame('confirmado', $aprovado['status']);

        $livro = $this->tenantJson('GET', '/api/v1/ecc/financeiro?ano=2026')
            ->assertOk()
            ->json('data');

        $this->assertEquals(150.5, $livro['saldo_final']);
    }

    public function test_rejeitar_libera_nova_submissao(): void
    {
        $this->asLider();
        $id = $this->postDeposito()->assertCreated()->json('data.id');

        $this->asAdmin();
        $this->tenantJson('POST', "/api/v1/ecc/financeiro/depositos/{$id}/rejeitar", [
            'motivo' => 'Comprovante ilegível',
        ])->assertOk()->assertJsonPath('data.status', 'rejeitado');

        $livro = $this->tenantJson('GET', '/api/v1/ecc/financeiro?ano=2026')
            ->assertOk()
            ->json('data');
        $this->assertEquals(0, $livro['saldo_final']);

        $this->asLider();
        $this->postDeposito(['valor' => '200'])->assertCreated();
    }

    public function test_lider_nao_aprova(): void
    {
        $this->asLider();
        $id = $this->postDeposito()->assertCreated()->json('data.id');

        $this->tenantJson('POST', "/api/v1/ecc/financeiro/depositos/{$id}/aprovar")
            ->assertForbidden();
    }

    public function test_lider_atualiza_provisionado_e_baixa_comprovante(): void
    {
        $this->asLider();
        $id = $this->postDeposito()->assertCreated()->json('data.id');

        $this->withHeaders([
            'X-Tenant' => 'paroquia-deposito',
            'Accept' => 'application/json',
        ])
            ->post("/api/v1/ecc/financeiro/depositos/{$id}", [
                'valor' => '180.00',
                'comprovante' => UploadedFile::fake()->image('novo.jpg'),
            ])
            ->assertOk()
            ->assertJsonPath('data.valor', 180);

        $this->withHeaders([
            'X-Tenant' => 'paroquia-deposito',
            'Accept' => 'application/json',
        ])
            ->get("/api/v1/ecc/financeiro/depositos/{$id}/comprovante")
            ->assertOk();
    }

    public function test_gestor_lista_pendentes_e_lider_so_a_sua_equipe(): void
    {
        $this->asLider();
        $this->postDeposito()->assertCreated();

        $listaLider = $this->tenantJson('GET', '/api/v1/ecc/financeiro/depositos')
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $listaLider);

        $this->asAdmin();
        $pendentes = $this->tenantJson('GET', '/api/v1/ecc/financeiro/depositos?status=provisionado')
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $pendentes);
    }
}
