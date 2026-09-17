<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EventoAgenda;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EscalaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private SuperAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = SuperAdmin::query()->create([
            'name' => 'Admin',
            'email' => 'admin-escala@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Demo Escala',
            'slug' => 'demo-escala',
        ]);

        $response->assertCreated();
        $this->tenant = Tenant::query()->where('slug', 'demo-escala')->firstOrFail();
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
        return $this->withHeader('X-Tenant', 'demo-escala')->json($method, $uri, $data);
    }

    public function test_escalas_requer_tenant_header(): void
    {
        $this->getJson('/api/v1/escalas/tipos')->assertStatus(400);
    }

    public function test_fluxo_completo_tipo_equipe_ocorrencia_atribuicao(): void
    {
        $tipo = $this->tenantJson('POST', '/api/v1/escalas/tipos', [
            'nome' => 'Liturgia semanal',
            'descricao' => 'Apoio às missas',
            'unidade_preferida' => 'ambos',
            'recorrencia' => 'semanal',
        ])->assertCreated()
            ->json('data');

        $this->assertSame('Liturgia semanal', $tipo['nome']);

        $equipe = $this->tenantJson('POST', '/api/v1/escalas/tipos/'.$tipo['id'].'/equipes', [
            'nome' => 'Leitura',
            'cor' => '#4E1220',
            'ordem' => 1,
            'vagas_sugeridas' => 2,
        ])->assertCreated()
            ->json('data');

        $equipe2 = $this->tenantJson('POST', '/api/v1/escalas/tipos/'.$tipo['id'].'/equipes', [
            'nome' => 'Acolhida',
            'ordem' => 2,
        ])->assertCreated()
            ->json('data');

        $ocorrencia = $this->tenantJson('POST', '/api/v1/escalas/tipos/'.$tipo['id'].'/ocorrencias', [
            'titulo' => 'Missa 10h',
            'inicia_em' => '2026-09-20T10:00:00-03:00',
            'local' => 'Igreja Matriz',
        ])->assertCreated()
            ->json('data');

        $this->assertNotEmpty($ocorrencia['evento_agenda_id']);
        $this->assertSame('Liturgia semanal', $ocorrencia['tipo_nome']);

        $this->tenant->run(function () use ($ocorrencia) {
            $evento = EventoAgenda::query()->findOrFail($ocorrencia['evento_agenda_id']);
            $this->assertSame('escalas', $evento->dono_modulo);
            $this->assertSame('escala', $evento->tipo);
            $this->assertSame($ocorrencia['id'], $evento->referencia_id);
        });

        $casal = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'nome' => 'João Silva',
            'nome_conjuge' => 'Maria Silva',
        ])->assertCreated()
            ->json('data');

        $atr1 = $this->tenantJson('POST', '/api/v1/escalas/ocorrencias/'.$ocorrencia['id'].'/atribuicoes', [
            'escala_equipe_id' => $equipe['id'],
            'casal_id' => $casal['id'],
        ])->assertCreated()
            ->json('data');

        $this->assertSame($casal['id'], $atr1['casal_id']);

        // Mesmo casal em outro ministério na mesma ocorrência — permitido
        $this->tenantJson('POST', '/api/v1/escalas/ocorrencias/'.$ocorrencia['id'].'/atribuicoes', [
            'escala_equipe_id' => $equipe2['id'],
            'casal_id' => $casal['id'],
        ])->assertCreated();

        // Duplicata mesma equipe — 422
        $this->tenantJson('POST', '/api/v1/escalas/ocorrencias/'.$ocorrencia['id'].'/atribuicoes', [
            'escala_equipe_id' => $equipe['id'],
            'casal_id' => $casal['id'],
        ])->assertStatus(422);

        // Pessoa avulsa
        $candidatos = $this->tenantJson('GET', '/api/v1/escalas/candidatos')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($candidatos['pessoas']);
        $pessoaId = $candidatos['pessoas'][0]['id'];

        $this->tenantJson('POST', '/api/v1/escalas/ocorrencias/'.$ocorrencia['id'].'/atribuicoes', [
            'escala_equipe_id' => $equipe['id'],
            'pessoa_id' => $pessoaId,
        ])->assertCreated();

        // XOR inválido
        $this->tenantJson('POST', '/api/v1/escalas/ocorrencias/'.$ocorrencia['id'].'/atribuicoes', [
            'escala_equipe_id' => $equipe2['id'],
            'pessoa_id' => $pessoaId,
            'casal_id' => $casal['id'],
        ])->assertStatus(422);

        $detalhe = $this->tenantJson('GET', '/api/v1/escalas/ocorrencias/'.$ocorrencia['id'])
            ->assertOk()
            ->json('data');

        $this->assertCount(3, $detalhe['atribuicoes']);

        $agenda = $this->tenantJson('GET', '/api/v1/escalas/agenda?from=2026-09-01T00:00:00Z&to=2026-09-30T23:59:59Z')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $agenda);

        $this->tenantJson('DELETE', '/api/v1/escalas/ocorrencias/'.$ocorrencia['id'])
            ->assertNoContent();

        $this->tenant->run(function () use ($ocorrencia) {
            $this->assertNull(EventoAgenda::query()->find($ocorrencia['evento_agenda_id']));
        });
    }

    public function test_gerar_ocorrencias_semanais(): void
    {
        $tipo = $this->tenantJson('POST', '/api/v1/escalas/tipos', [
            'nome' => 'Coroinhas',
        ])->assertCreated()->json('data');

        $lista = $this->tenantJson('POST', '/api/v1/escalas/tipos/'.$tipo['id'].'/ocorrencias/gerar', [
            'inicio' => '2026-09-06T09:00:00-03:00',
            'fim' => '2026-09-27T09:00:00-03:00',
            'frequencia' => 'semanal',
            'local' => 'Capela',
        ])->assertCreated()
            ->json('data');

        $this->assertCount(4, $lista);
    }

    public function test_tipo_nome_duplicado_retorna_422(): void
    {
        $this->tenantJson('POST', '/api/v1/escalas/tipos', [
            'nome' => 'Liturgia',
        ])->assertCreated();

        $this->tenantJson('POST', '/api/v1/escalas/tipos', [
            'nome' => 'Liturgia',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nome']);
    }

    public function test_pode_excluir_tipo(): void
    {
        $tipo = $this->tenantJson('POST', '/api/v1/escalas/tipos', [
            'nome' => 'Para excluir',
        ])->assertCreated()->json('data');

        $this->tenantJson('DELETE', '/api/v1/escalas/tipos/'.$tipo['id'])
            ->assertNoContent();

        $this->tenantJson('GET', '/api/v1/escalas/tipos/'.$tipo['id'])
            ->assertNotFound();
    }
}
