<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SuperAdmin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CalendarioOficialTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private SuperAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = SuperAdmin::query()->create([
            'name' => 'Admin',
            'email' => 'admin-cal@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Demo Calendario',
            'slug' => 'demo-calendario',
        ]);

        $response->assertCreated();
        $this->tenant = Tenant::query()->where('slug', 'demo-calendario')->firstOrFail();
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
        return $this->withHeader('X-Tenant', 'demo-calendario')->json($method, $uri, $data);
    }

    public function test_fluxo_calendario_coleta_fechamento_pdf(): void
    {
        $local = $this->tenantJson('POST', '/api/v1/calendario/locais', [
            'nome' => 'MATRIZ',
            'ordem' => 1,
        ])->assertCreated()
            ->json('data');

        $this->tenantJson('POST', '/api/v1/calendario/slots-padrao', [
            'local_id' => $local['id'],
            'dia_semana' => 0,
            'hora' => '09:30',
            'secao' => 'fds',
        ])->assertCreated();

        $mensal = $this->tenantJson('POST', '/api/v1/calendario/mensais', [
            'ano' => 2026,
            'mes' => 9,
            'titulo' => 'Paróquia São José Operário',
            'subtitulo' => 'Jundiaí / Diocese de Jundiaí - SP',
        ])->assertCreated()
            ->json('data');

        $this->assertSame('rascunho', $mensal['status']);
        $this->assertNotEmpty($mensal['itens']);
        $this->assertNotEmpty($mensal['tempos_liturgicos']);
        $this->assertCount(4, $mensal['tempos_liturgicos']); // Set/2026 tem 4 domingos

        $obs1 = $this->tenantJson('POST', '/api/v1/calendario/mensais/'.$mensal['id'].'/observacoes', [
            'titulo' => '1º final de semana',
            'descricao' => 'Missa com crianças',
        ])->assertCreated()
            ->json('data');

        $this->tenantJson('POST', '/api/v1/calendario/mensais/'.$mensal['id'].'/observacoes', [
            'titulo' => '4º final de semana',
            'descricao' => 'Missa do ECC',
        ])->assertCreated();

        $tempoId = $mensal['tempos_liturgicos'][0]['id'];
        $this->tenantJson('PUT', '/api/v1/calendario/tempos-liturgicos/'.$tempoId, [
            'rotulo' => '23o Domingo do Tempo Comum',
        ])->assertOk()
            ->assertJsonPath('data.rotulo', '23o Domingo do Tempo Comum');

        $itemId = $mensal['itens'][0]['id'];
        $this->tenantJson('PUT', '/api/v1/calendario/itens/'.$itemId, [
            'celebrante_nome' => 'Pe. Rodolfo',
            'observacao_id' => $obs1['id'],
        ])->assertOk()
            ->assertJsonPath('celebrante_nome', 'Pe. Rodolfo')
            ->assertJsonPath('observacao_id', $obs1['id']);

        $tipos = $this->tenantJson('GET', '/api/v1/calendario/evento-tipos')
            ->assertOk()
            ->json('data');
        $this->assertNotEmpty($tipos);
        $tipoFesta = collect($tipos)->firstWhere('slug', 'festa');
        $this->assertNotEmpty($tipoFesta);

        $custom = $this->tenantJson('POST', '/api/v1/calendario/evento-tipos', [
            'nome' => 'Retiro',
            'secao_padrao' => 'festa',
        ])->assertCreated()->json('data');
        $this->assertSame('Retiro', $custom['nome']);
        $this->assertFalse($custom['sistema']);

        $this->tenantJson('PUT', '/api/v1/calendario/evento-tipos/'.$custom['id'], [
            'nome' => 'Retiro paroquial',
        ])->assertOk();

        $updated = $this->tenantJson('GET', '/api/v1/calendario/evento-tipos?todos=1')
            ->assertOk()
            ->json('data');
        $this->assertSame(
            'Retiro paroquial',
            collect($updated)->firstWhere('id', $custom['id'])['nome'] ?? null
        );

        $this->tenantJson('DELETE', '/api/v1/calendario/evento-tipos/'.$custom['id'])
            ->assertNoContent();

        $this->tenantJson('DELETE', '/api/v1/calendario/evento-tipos/'.$tipoFesta['id'])
            ->assertStatus(422);

        $this->tenantJson('POST', '/api/v1/calendario/mensais/'.$mensal['id'].'/itens', [
            'data' => '2026-09-14',
            'hora' => '19:30',
            'tipo_id' => $tipoFesta['id'],
            'titulo' => 'Solenidade de Santa Cruz',
            'celebrante_nome' => 'Pe. Rodolfo',
        ])->assertCreated()
            ->assertJsonPath('secao', 'festa');

        $this->tenantJson('POST', '/api/v1/calendario/mensais/'.$mensal['id'].'/status', [
            'status' => 'coleta',
        ])->assertOk()
            ->assertJsonPath('data.status', 'coleta');

        $coleta = $this->tenantJson('POST', '/api/v1/calendario/mensais/'.$mensal['id'].'/coleta-links', [
            'rotulo' => 'Diáconos e ministros',
        ])->assertCreated()
            ->json();

        $token = $coleta['token'];
        $this->assertNotEmpty($token);
        $this->assertSame($token, $coleta['data']['token'] ?? null);

        // Token permanece disponível no detalhe (persistência do link)
        $detailComLink = $this->tenantJson('GET', '/api/v1/calendario/mensais/'.$mensal['id'])
            ->assertOk()
            ->json('data');
        $this->assertNotEmpty($detailComLink['coleta_links']);
        $this->assertSame($token, $detailComLink['coleta_links'][0]['token']);

        // Reexibir não cria outro link
        $coletaAgain = $this->tenantJson('POST', '/api/v1/calendario/mensais/'.$mensal['id'].'/coleta-links', [
            'rotulo' => 'Diáconos e ministros',
        ])->assertCreated()->json();
        $this->assertSame($token, $coletaAgain['token']);
        $this->assertSame($coleta['data']['id'], $coletaAgain['data']['id']);

        Auth::forgetGuards();

        $this->withHeader('X-Tenant', 'demo-calendario')
            ->postJson('/api/v1/public/calendario/coleta/'.$token, [
                'nome_exibicao' => 'Diác. Gerson',
                'datas' => ['2026-09-15', '2026-09-22'],
                'motivo' => 'Viagem',
            ])
            ->assertCreated();

        Sanctum::actingAs($this->admin);

        $detail = $this->tenantJson('GET', '/api/v1/calendario/mensais/'.$mensal['id'])
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $detail['indisponibilidades']);

        $this->tenantJson('POST', '/api/v1/calendario/mensais/'.$mensal['id'].'/status', [
            'status' => 'montagem',
        ])->assertOk();

        $this->tenantJson('POST', '/api/v1/calendario/mensais/'.$mensal['id'].'/status', [
            'status' => 'fechado',
        ])->assertOk()
            ->assertJsonPath('data.status', 'fechado');

        $this->tenantJson('PUT', '/api/v1/calendario/itens/'.$itemId, [
            'celebrante_nome' => 'Pe. Marcos',
        ])->assertStatus(422);

        // Reabrir calendário fechado → montagem
        $this->tenantJson('POST', '/api/v1/calendario/mensais/'.$mensal['id'].'/status', [
            'status' => 'montagem',
        ])->assertOk()
            ->assertJsonPath('data.status', 'montagem')
            ->assertJsonPath('data.fechado_em', null);

        $this->tenantJson('PUT', '/api/v1/calendario/itens/'.$itemId, [
            'celebrante_nome' => 'Pe. Marcos',
        ])->assertOk();

        $festaId = collect($this->tenantJson('GET', '/api/v1/calendario/mensais/'.$mensal['id'])
            ->json('data.itens'))
            ->firstWhere('secao', 'festa')['id'];

        $this->tenantJson('DELETE', '/api/v1/calendario/itens/'.$festaId)
            ->assertNoContent();

        $itensAposDelete = $this->tenantJson('GET', '/api/v1/calendario/mensais/'.$mensal['id'])
            ->json('data.itens');
        $this->assertFalse(collect($itensAposDelete)->contains('id', $festaId));

        $this->tenantJson('POST', '/api/v1/calendario/mensais/'.$mensal['id'].'/status', [
            'status' => 'fechado',
        ])->assertOk();

        $this->tenantJson('DELETE', '/api/v1/calendario/itens/'.$itemId)
            ->assertStatus(422);

        tenancy()->initialize($this->tenant);
        $igreja = \App\Models\Igreja::query()->firstOrFail();
        $igreja->update([
            'cidade' => 'Jundiaí',
            'uf' => 'SP',
            'diocese' => 'Diocese de Jundiaí',
        ]);
        \App\Models\AppSetting::query()->updateOrCreate([], [
            'cores' => [
                'primary' => '#123456',
                'secondary' => '#ABCDEF',
                'text' => '#111111',
                'text_muted' => '#666666',
                'on_primary' => '#FFFFFF',
            ],
        ]);
        tenancy()->end();

        $pdf = $this->tenantJson('GET', '/api/v1/calendario/mensais/'.$mensal['id'].'/pdf');
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));
        $this->assertGreaterThan(1000, strlen((string) $pdf->getContent()));
    }

    public function test_excluir_e_copiar_proximo_mes(): void
    {
        $local = $this->tenantJson('POST', '/api/v1/calendario/locais', [
            'nome' => 'MATRIZ',
            'ordem' => 1,
        ])->assertCreated()->json('data');

        $this->tenantJson('POST', '/api/v1/calendario/slots-padrao', [
            'local_id' => $local['id'],
            'dia_semana' => 0,
            'hora' => '09:30',
            'secao' => 'fds',
        ])->assertCreated();

        $mensal = $this->tenantJson('POST', '/api/v1/calendario/mensais', [
            'ano' => 2026,
            'mes' => 9,
            'titulo' => 'Paróquia Teste',
            'subtitulo' => 'Cidade / Diocese',
        ])->assertCreated()->json('data');

        $obs = $this->tenantJson('POST', '/api/v1/calendario/mensais/'.$mensal['id'].'/observacoes', [
            'titulo' => 'ECC',
            'descricao' => 'Missa do ECC',
        ])->assertCreated()->json('data');

        $itemId = $mensal['itens'][0]['id'];
        $this->tenantJson('PUT', '/api/v1/calendario/itens/'.$itemId, [
            'celebrante_nome' => 'Pe. Marcos',
            'observacao_id' => $obs['id'],
        ])->assertOk();

        $copia = $this->tenantJson('POST', '/api/v1/calendario/mensais/'.$mensal['id'].'/copiar-proximo')
            ->assertCreated()
            ->json('data');

        $this->assertSame(2026, $copia['ano']);
        $this->assertSame(10, $copia['mes']);
        $this->assertSame('rascunho', $copia['status']);
        $this->assertSame('Paróquia Teste', $copia['titulo']);
        $this->assertCount(1, $copia['observacoes']);
        $this->assertSame('ECC', $copia['observacoes'][0]['titulo']);
        $this->assertNotEmpty($copia['itens']);

        $copiado = collect($copia['itens'])->first(
            fn ($i) => ($i['celebrante_nome'] ?? null) === 'Pe. Marcos'
        );
        $this->assertNotNull($copiado);
        $this->assertNotNull($copiado['observacao_id']);
        $this->assertNotSame($obs['id'], $copiado['observacao_id']);

        $this->tenantJson('POST', '/api/v1/calendario/mensais/'.$mensal['id'].'/copiar-proximo')
            ->assertStatus(422);

        $this->tenantJson('DELETE', '/api/v1/calendario/mensais/'.$copia['id'])
            ->assertNoContent();

        $lista = $this->tenantJson('GET', '/api/v1/calendario/mensais')->assertOk()->json('data');
        $this->assertFalse(collect($lista)->contains('id', $copia['id']));
        $this->assertTrue(collect($lista)->contains('id', $mensal['id']));
    }

    public function test_calendario_requer_auth(): void
    {
        Auth::forgetGuards();

        $this->withHeader('X-Tenant', 'demo-calendario')
            ->getJson('/api/v1/calendario/mensais')
            ->assertUnauthorized();
    }
}
