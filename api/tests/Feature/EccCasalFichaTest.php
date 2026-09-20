<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EccEquipeServico;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EccCasalFichaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private SuperAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = SuperAdmin::query()->create([
            'name' => 'Admin',
            'email' => 'admin-ficha@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Demo Ficha',
            'slug' => 'demo-ficha',
        ]);

        $response->assertCreated();
        $this->tenant = Tenant::query()->where('slug', 'demo-ficha')->firstOrFail();
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
        return $this->withHeader('X-Tenant', 'demo-ficha')->json($method, $uri, $data);
    }

    public function test_equipes_servico_seeded_and_listed(): void
    {
        $list = $this->tenantJson('GET', '/api/v1/ecc/equipes-servico')
            ->assertOk()
            ->json('data');

        $this->assertCount(12, $list);
        $this->assertSame('Coordenação Geral', $list[0]['nome']);
        $this->assertSame('Liturgia/Vigília', $list[2]['nome']);
        $this->assertSame('Palestras', $list[11]['nome']);
    }

    public function test_equipes_servico_requires_auth(): void
    {
        Auth::forgetGuards();

        $this->withHeader('X-Tenant', 'demo-ficha')
            ->getJson('/api/v1/ecc/equipes-servico')
            ->assertUnauthorized();
    }

    public function test_create_casal_with_enriched_ficha(): void
    {
        $equipes = $this->tenantJson('GET', '/api/v1/ecc/equipes-servico')->json('data');
        $cozinha = collect($equipes)->firstWhere('slug', 'cozinha');
        $cafe = collect($equipes)->firstWhere('slug', 'cafe-e-minimercado');
        $this->assertNotNull($cozinha);
        $this->assertNotNull($cafe);

        $casal = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'nome' => 'João Silva',
            'nome_conjuge' => 'Maria Silva',
            'ele' => [
                'nome_usual' => 'Jão',
                'profissao' => 'Engenheiro',
                'religiao' => 'Católica',
                'endereco_profissional' => 'Av. Paulista, 100',
                'telefone_profissional' => '11999990001',
            ],
            'ela' => [
                'nome_usual' => 'Mari',
                'profissao' => 'Professora',
                'religiao' => 'Católica',
                'endereco_profissional' => 'Rua B, 20',
                'telefone_profissional' => '11999990002',
            ],
            'engajamento_paroquial' => 'Catequese e conselho',
            'habilidades' => 'Música, cozinha',
            'etapas' => [
                ['etapa' => 1, 'ecc_numero' => '12', 'data' => '2018-05-12', 'local' => 'Paróquia São José'],
                ['etapa' => 2, 'ecc_numero' => '20', 'data' => '2020-09-01', 'local' => 'Salão'],
            ],
            'atividades' => [
                ['ecc_numero' => '34', 'equipe_servico_id' => $cozinha['id'], 'status' => 'A'],
                ['ecc_numero' => '35', 'equipe_servico_id' => $cafe['id'], 'status' => 'C', 'observacao' => 'Café'],
            ],
            'preferencias' => [
                ['equipe_servico_id' => $cozinha['id'], 'ordem' => 1],
                ['equipe_servico_id' => $cafe['id'], 'ordem' => 2],
            ],
        ])->assertCreated()
            ->json('data');

        $this->assertSame('Jão', $casal['ele']['nome_usual']);
        $this->assertSame('Engenheiro', $casal['ele']['profissao']);
        $this->assertSame('Mari', $casal['ela']['nome_usual']);
        $this->assertSame('Catequese e conselho', $casal['engajamento_paroquial']);
        $this->assertSame('Música, cozinha', $casal['habilidades']);
        $this->assertCount(2, $casal['etapas']);
        $this->assertSame(1, $casal['etapas'][0]['etapa']);
        $this->assertSame('12', $casal['etapas'][0]['ecc_numero']);
        $this->assertCount(2, $casal['atividades']);
        $this->assertSame('A', $casal['atividades'][0]['status']);
        $this->assertSame('Cozinha', $casal['atividades'][0]['equipe_servico_nome']);
        $this->assertCount(2, $casal['preferencias']);
        $this->assertSame(1, $casal['preferencias'][0]['ordem']);

        $show = $this->tenantJson('GET', '/api/v1/ecc/casais/'.$casal['id'])
            ->assertOk()
            ->json('data');

        $this->assertSame('11999990001', $show['ele']['telefone_profissional']);
        $this->assertSame('C', $show['atividades'][1]['status']);
    }

    public function test_update_syncs_etapas_atividades_preferencias(): void
    {
        $equipes = $this->tenantJson('GET', '/api/v1/ecc/equipes-servico')->json('data');
        $sala = collect($equipes)->firstWhere('slug', 'sala');
        $cozinha = collect($equipes)->firstWhere('slug', 'cozinha');

        $casal = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'nome' => 'Pedro',
            'nome_conjuge' => 'Ana',
            'etapas' => [
                ['etapa' => 1, 'ecc_numero' => '1', 'data' => '2010-01-01', 'local' => 'A'],
            ],
            'atividades' => [
                ['ecc_numero' => '10', 'equipe_servico_id' => $sala['id'], 'status' => 'A'],
            ],
            'preferencias' => [
                ['equipe_servico_id' => $sala['id'], 'ordem' => 1],
            ],
        ])->assertCreated()->json('data');

        $updated = $this->tenantJson('PUT', '/api/v1/ecc/casais/'.$casal['id'], [
            'nome' => 'Pedro',
            'nome_conjuge' => 'Ana',
            'etapas' => [
                ['etapa' => 1, 'ecc_numero' => '1', 'data' => '2010-01-01', 'local' => 'A'],
                ['etapa' => 3, 'ecc_numero' => '30', 'data' => '2022-06-15', 'local' => 'B'],
            ],
            'atividades' => [
                ['ecc_numero' => '34', 'equipe_servico_id' => $cozinha['id'], 'status' => 'IC'],
            ],
            'preferencias' => [
                ['equipe_servico_id' => $cozinha['id'], 'ordem' => 1],
                ['equipe_servico_id' => $sala['id'], 'ordem' => 2],
            ],
        ])->assertOk()->json('data');

        $this->assertCount(2, $updated['etapas']);
        $this->assertSame(3, $updated['etapas'][1]['etapa']);
        $this->assertCount(1, $updated['atividades']);
        $this->assertSame('IC', $updated['atividades'][0]['status']);
        $this->assertCount(2, $updated['preferencias']);
    }

    public function test_validation_rejects_invalid_status_and_etapa(): void
    {
        $equipes = $this->tenantJson('GET', '/api/v1/ecc/equipes-servico')->json('data');
        $cozinha = collect($equipes)->firstWhere('slug', 'cozinha');

        $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'nome' => 'X',
            'nome_conjuge' => 'Y',
            'etapas' => [
                ['etapa' => 9, 'ecc_numero' => '1'],
            ],
        ])->assertStatus(422);

        $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'nome' => 'X',
            'nome_conjuge' => 'Y',
            'atividades' => [
                ['ecc_numero' => '1', 'equipe_servico_id' => $cozinha['id'], 'status' => 'ZZ'],
            ],
        ])->assertStatus(422);
    }

    public function test_show_returns_structured_etapas(): void
    {
        $casal = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'nome' => 'Legacy',
            'nome_conjuge' => 'Pair',
            'etapas' => [
                ['etapa' => 2, 'ecc_numero' => '11º'],
                ['etapa' => 3, 'ecc_numero' => '22º'],
            ],
        ])->assertCreated()->json('data');

        $this->assertNotEmpty($casal['etapas']);
        $etapa2 = collect($casal['etapas'])->firstWhere('etapa', 2);
        $etapa3 = collect($casal['etapas'])->firstWhere('etapa', 3);
        $this->assertSame('11º', $etapa2['ecc_numero']);
        $this->assertSame('22º', $etapa3['ecc_numero']);
    }

    public function test_new_igreja_gets_default_equipes_servico(): void
    {
        $igreja = $this->tenantJson('POST', '/api/v1/igrejas', [
            'nome' => 'Nova Paróquia',
            'tipo' => 'paroquia',
        ])->assertCreated()->json('data');

        $this->tenant->run(function () use ($igreja): void {
            $count = EccEquipeServico::query()
                ->where('igreja_id', $igreja['id'])
                ->count();
            $this->assertSame(12, $count);
        });
    }
}
