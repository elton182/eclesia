<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EventoAgenda;
use App\Models\EventoTipo;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EccEventoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private SuperAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = SuperAdmin::query()->create([
            'name' => 'Admin',
            'email' => 'admin-eventos@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Demo Eventos',
            'slug' => 'demo-eventos',
        ]);

        $response->assertCreated();
        $this->tenant = Tenant::query()->where('slug', 'demo-eventos')->firstOrFail();
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
        return $this->withHeader('X-Tenant', 'demo-eventos')->json($method, $uri, $data);
    }

    public function test_eventos_requires_tenant_header(): void
    {
        $this->getJson('/api/v1/ecc/eventos')->assertStatus(400);
    }

    public function test_tipos_cadastraveis_e_crud_sincroniza_agenda(): void
    {
        $tipos = $this->tenantJson('GET', '/api/v1/eventos/tipos')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($tipos);
        $servos = collect($tipos)->firstWhere('codigo', 'servos');
        $this->assertNotNull($servos);

        $evento = $this->tenantJson('POST', '/api/v1/ecc/eventos', [
            'titulo' => 'Reunião de servos',
            'evento_tipo_id' => $servos['id'],
            'inicia_em' => '2026-10-05T20:00:00-03:00',
            'local' => 'Salão paroquial',
        ])->assertCreated()
            ->json('data');

        $this->assertSame('servos', $evento['tipo']);
        $this->assertSame('ecc', $evento['origem']);
        $this->assertNotEmpty($evento['evento_agenda_id']);

        tenancy()->initialize($this->tenant);
        $agenda = EventoAgenda::query()->findOrFail($evento['evento_agenda_id']);
        $this->assertSame('ecc', $agenda->dono_modulo);
        tenancy()->end();

        $novoTipo = $this->tenantJson('POST', '/api/v1/eventos/tipos', [
            'nome' => 'Retiro',
            'abrev' => 'Ret',
            'cor' => '#123456',
            'escopo' => 'geral',
        ])->assertCreated()->json('data');

        $geral = $this->tenantJson('POST', '/api/v1/eventos', [
            'titulo' => 'Festa da padroeira',
            'evento_tipo_id' => $novoTipo['id'],
            'inicia_em' => '2026-08-15T10:00:00-03:00',
        ])->assertCreated()->json('data');

        $this->assertSame('geral', $geral['origem']);

        $this->tenantJson('GET', '/api/v1/eventos')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->tenantJson('GET', '/api/v1/ecc/eventos')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_participantes_convidados_e_compras(): void
    {
        $tipos = $this->tenantJson('GET', '/api/v1/eventos/tipos')->json('data');
        $anual = collect($tipos)->firstWhere('codigo', 'anual');

        $equipe = $this->tenantJson('POST', '/api/v1/ecc/equipes', [
            'nome' => 'Equipe A',
        ])->assertCreated()->json('data');

        $casal = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'equipe_id' => $equipe['id'],
            'nome' => 'João',
            'nome_conjuge' => 'Maria',
        ])->assertCreated()->json('data');

        $evento = $this->tenantJson('POST', '/api/v1/ecc/eventos', [
            'titulo' => 'Jornada anual',
            'evento_tipo_id' => $anual['id'],
            'inicia_em' => '2026-11-15T09:00:00-03:00',
            'casal_compras_id' => $casal['id'],
        ])->assertCreated()->json('data');

        $this->assertTrue($evento['permite_compras']);

        $comPart = $this->tenantJson('POST', '/api/v1/ecc/eventos/'.$evento['id'].'/participantes', [
            'casal_id' => $casal['id'],
            'convidados' => 3,
        ])->assertCreated()->json('data');

        $this->assertSame(3, $comPart['participantes'][0]['convidados']);
        $this->assertSame(3, $comPart['convidados_total']);

        $this->tenantJson('PUT', '/api/v1/ecc/eventos/'.$evento['id'].'/participantes', [
            'casal_id' => $casal['id'],
            'convidados' => 5,
        ])->assertOk()
            ->assertJsonPath('data.participantes.0.convidados', 5);

        $item = $this->tenantJson('POST', '/api/v1/ecc/eventos/'.$evento['id'].'/itens-compra', [
            'nome' => 'Água',
            'qtd' => 10,
            'unidade' => 'fardo',
        ])->assertCreated()->json('data');

        $this->tenantJson('POST', '/api/v1/ecc/eventos/'.$evento['id'].'/caixa/doacoes', [
            'casal_id' => $casal['id'],
            'valor' => 100,
            'descricao' => 'Doação João e Maria',
        ])->assertCreated();

        $doacaoOutro = $this->tenantJson('POST', '/api/v1/ecc/eventos/'.$evento['id'].'/caixa/doacoes', [
            'doador_nome' => 'Padre João',
            'valor' => 50,
            'descricao' => 'Doação da paróquia',
        ])->assertCreated()->json('data');

        $this->assertSame('Padre João', $doacaoOutro['doador_nome']);
        $this->assertNull($doacaoOutro['casal_id']);
        $this->assertNull($doacaoOutro['ecc_equipe_id']);

        $this->tenantJson('POST', '/api/v1/ecc/eventos/'.$evento['id'].'/caixa/doacoes', [
            'valor' => 10,
        ])->assertStatus(422);

        $this->tenantJson('POST', '/api/v1/ecc/eventos/'.$evento['id'].'/itens-compra/'.$item['id'].'/comprar', [
            'valor_gasto' => 85.5,
        ])->assertOk()
            ->assertJsonPath('data.status', 'comprado');

        $caixa = $this->tenantJson('GET', '/api/v1/ecc/eventos/'.$evento['id'].'/caixa')
            ->assertOk()
            ->json('data');

        $this->assertEquals(150, $caixa['total_entradas']);
        $this->assertEquals(85.5, $caixa['total_saidas']);
        $this->assertEqualsWithDelta(64.5, $caixa['saldo'], 0.01);
        $this->assertSame(1, $caixa['itens']['comprados']);

        $extratoOutro = collect($caixa['extrato'])->first(
            fn ($m) => ($m['doador']['tipo'] ?? null) === 'outro'
        );
        $this->assertNotNull($extratoOutro);
        $this->assertSame('Padre João', $extratoOutro['doador']['rotulo']);

        // Evento ECC (não anual) também permite compras
        $servos = collect($tipos)->firstWhere('codigo', 'servos');
        $evServos = $this->tenantJson('POST', '/api/v1/ecc/eventos', [
            'titulo' => 'Reunião servos',
            'evento_tipo_id' => $servos['id'],
            'inicia_em' => '2026-10-01T20:00:00-03:00',
        ])->assertCreated()->json('data');
        $this->assertTrue($evServos['permite_compras']);

        $this->tenantJson('POST', '/api/v1/ecc/eventos/'.$evServos['id'].'/itens-compra', [
            'nome' => 'Café',
        ])->assertCreated();
    }

    public function test_excluir_evento_remove_agenda(): void
    {
        $tipos = $this->tenantJson('GET', '/api/v1/eventos/tipos')->json('data');
        $servos = collect($tipos)->firstWhere('codigo', 'servos');

        $evento = $this->tenantJson('POST', '/api/v1/ecc/eventos', [
            'titulo' => 'Para excluir',
            'evento_tipo_id' => $servos['id'],
            'inicia_em' => '2026-12-01T19:00:00-03:00',
        ])->assertCreated()->json('data');

        $agendaId = $evento['evento_agenda_id'];
        $this->assertNotEmpty($agendaId);

        $this->tenantJson('DELETE', '/api/v1/ecc/eventos/'.$evento['id'])
            ->assertNoContent();

        $this->tenantJson('GET', '/api/v1/ecc/eventos/'.$evento['id'])
            ->assertNotFound();

        tenancy()->initialize($this->tenant);
        $this->assertNull(EventoAgenda::query()->find($agendaId));
        tenancy()->end();
    }
}
