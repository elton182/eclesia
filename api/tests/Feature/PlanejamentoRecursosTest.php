<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Igreja;
use App\Models\Pastoral;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlanejamentoRecursosTest extends TestCase
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
            'email' => 'admin-recursos@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($this->admin);

        $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Demo Recursos',
            'slug' => 'demo-recursos',
        ])->assertCreated();

        $this->tenant = Tenant::query()->where('slug', 'demo-recursos')->firstOrFail();

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
    private function tenantJson(string $method, string $uri, array $data = [], ?string $igrejaId = null)
    {
        return $this->withHeaders([
            'X-Tenant' => 'demo-recursos',
            'X-Igreja' => $igrejaId ?? $this->igrejaId,
        ])->json($method, $uri, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function publicJson(string $method, string $uri, array $data = [])
    {
        Auth::forgetGuards();
        $this->app['auth']->forgetGuards();

        return $this->withHeaders(['X-Tenant' => 'demo-recursos'])->json($method, $uri, $data);
    }

    private function actingAsRole(string $role, string $email): User
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function criarRecurso(array $data): array
    {
        Sanctum::actingAs($this->admin);

        return $this->tenantJson('POST', '/api/v1/planejamento/recursos', $data)
            ->assertCreated()
            ->json('data');
    }

    /**
     * @return array{anualId: string, pastoralA: string, pastoralB: string}
     */
    private function seedAno(): array
    {
        Sanctum::actingAs($this->admin);

        $pastoralA = $this->tenantJson('POST', '/api/v1/pastorais', ['nome' => 'Pastoral A'])
            ->assertCreated()->json('data.id');
        $pastoralB = $this->tenantJson('POST', '/api/v1/pastorais', ['nome' => 'Pastoral B'])
            ->assertCreated()->json('data.id');

        $anualId = $this->tenantJson('POST', '/api/v1/planejamento/anuais', ['ano' => 2026])
            ->assertCreated()->json('data.id');

        $this->tenantJson('POST', '/api/v1/planejamento/anuais/'.$anualId.'/status', [
            'status' => 'coleta',
        ])->assertOk();

        return compact('anualId', 'pastoralA', 'pastoralB');
    }

    /**
     * @param  list<array{recurso_id: string, quantidade?: int}>  $recursos
     * @param  array<string, mixed>  $extra
     */
    private function criarEvento(string $anualId, string $pastoralId, array $recursos, array $extra = [])
    {
        return $this->tenantJson('POST', '/api/v1/planejamento/anuais/'.$anualId.'/eventos', [
            'pastoral_id' => $pastoralId,
            'titulo' => $extra['titulo'] ?? 'Evento',
            'data_inicio' => $extra['data_inicio'] ?? '2026-03-10',
            'hora_inicio' => array_key_exists('hora_inicio', $extra) ? $extra['hora_inicio'] : '19:00',
            'hora_fim' => array_key_exists('hora_fim', $extra) ? $extra['hora_fim'] : '21:00',
            'recursos' => $recursos,
            ...array_diff_key($extra, array_flip(['titulo', 'data_inicio', 'hora_inicio', 'hora_fim'])),
        ]);
    }

    public function test_crud_recursos_e_permissoes(): void
    {
        Auth::forgetGuards();
        $this->app['auth']->forgetGuards();
        $this->tenantJson('GET', '/api/v1/planejamento/recursos')->assertUnauthorized();

        $sala = $this->criarRecurso(['nome' => 'Sala 1', 'tipo' => 'espaco', 'quantidade' => 5]);
        $this->assertSame(1, $sala['quantidade']);
        $this->assertSame('espaco', $sala['tipo']);
        $this->assertTrue($sala['ativo']);

        $projetor = $this->criarRecurso(['nome' => 'Projetor móvel', 'tipo' => 'recurso', 'quantidade' => 2]);
        $this->assertSame(2, $projetor['quantidade']);

        $this->tenantJson('POST', '/api/v1/planejamento/recursos', [
            'nome' => 'Sala 1',
            'tipo' => 'espaco',
        ])->assertStatus(422);

        $this->tenantJson('POST', '/api/v1/planejamento/recursos', [
            'nome' => 'Sem tipo',
        ])->assertStatus(422);

        $this->tenantJson('PATCH', '/api/v1/planejamento/recursos/'.$projetor['id'], [
            'quantidade' => 3,
            'descricao' => 'Epson',
        ])->assertOk()
            ->assertJsonPath('data.quantidade', 3)
            ->assertJsonPath('data.descricao', 'Epson');

        $this->tenantJson('PATCH', '/api/v1/planejamento/recursos/'.$projetor['id'], [
            'ativo' => false,
        ])->assertOk()->assertJsonPath('data.ativo', false);

        $this->assertCount(2, $this->tenantJson('GET', '/api/v1/planejamento/recursos')->assertOk()->json('data'));
        $this->assertCount(1, $this->tenantJson('GET', '/api/v1/planejamento/recursos?tipo=espaco')->json('data'));

        $this->actingAsRole('coordenador-pastoral', 'coord-recursos@test.local');
        $lista = $this->tenantJson('GET', '/api/v1/planejamento/recursos')->assertOk()->json('data');
        $this->assertCount(1, $lista);
        $this->assertSame('Sala 1', $lista[0]['nome']);

        $this->tenantJson('POST', '/api/v1/planejamento/recursos', [
            'nome' => 'Sala 2',
            'tipo' => 'espaco',
        ])->assertForbidden();
        $this->tenantJson('PATCH', '/api/v1/planejamento/recursos/'.$sala['id'], ['nome' => 'X'])->assertForbidden();
        $this->tenantJson('DELETE', '/api/v1/planejamento/recursos/'.$sala['id'])->assertForbidden();

        $this->actingAsRole('lider-equipe', 'lider-recursos@test.local');
        $this->tenantJson('GET', '/api/v1/planejamento/recursos')->assertForbidden();

        Sanctum::actingAs($this->admin);
        $this->tenantJson('DELETE', '/api/v1/planejamento/recursos/'.$sala['id'])->assertNoContent();
        $this->tenantJson('PATCH', '/api/v1/planejamento/recursos/'.$sala['id'], ['nome' => 'Y'])->assertNotFound();
    }

    public function test_delete_de_recurso_usado_apenas_inativa(): void
    {
        $base = $this->seedAno();
        $sala = $this->criarRecurso(['nome' => 'Salão', 'tipo' => 'espaco']);

        $this->criarEvento($base['anualId'], $base['pastoralA'], [['recurso_id' => $sala['id']]])->assertCreated();

        $this->tenantJson('DELETE', '/api/v1/planejamento/recursos/'.$sala['id'])
            ->assertOk()
            ->assertJsonPath('data.ativo', false);

        $this->criarEvento($base['anualId'], $base['pastoralB'], [['recurso_id' => $sala['id']]], [
            'data_inicio' => '2026-04-01',
        ])->assertStatus(422)->assertJsonValidationErrors('recursos');
    }

    public function test_isolamento_por_igreja(): void
    {
        $sala = $this->criarRecurso(['nome' => 'Sala Matriz', 'tipo' => 'espaco']);

        tenancy()->initialize($this->tenant);
        $outraId = Igreja::query()->create(['nome' => 'Outra', 'tipo' => 'paroquia'])->id;
        tenancy()->end();

        $this->assertCount(0, $this->tenantJson('GET', '/api/v1/planejamento/recursos', [], $outraId)->json('data'));
        $this->tenantJson('PATCH', '/api/v1/planejamento/recursos/'.$sala['id'], ['nome' => 'X'], $outraId)
            ->assertNotFound();
    }

    public function test_salas_diferentes_coexistem_e_mesma_sala_bloqueia(): void
    {
        $base = $this->seedAno();
        $sala1 = $this->criarRecurso(['nome' => 'Sala 1', 'tipo' => 'espaco']);
        $sala2 = $this->criarRecurso(['nome' => 'Sala 2', 'tipo' => 'espaco']);

        $primeiro = $this->criarEvento($base['anualId'], $base['pastoralA'], [['recurso_id' => $sala1['id']]], [
            'titulo' => 'Reunião A',
        ])->assertCreated()
            ->assertJsonPath('data.recursos.0.recurso_id', $sala1['id'])
            ->assertJsonPath('data.recursos.0.nome', 'Sala 1')
            ->json('data');

        $this->criarEvento($base['anualId'], $base['pastoralB'], [['recurso_id' => $sala2['id']]], [
            'hora_inicio' => '20:00',
            'hora_fim' => '22:00',
        ])->assertCreated();

        $resp = $this->criarEvento($base['anualId'], $base['pastoralB'], [['recurso_id' => $sala1['id']]], [
            'hora_inicio' => '20:00',
            'hora_fim' => '22:00',
        ])->assertStatus(422)->assertJsonValidationErrors('recursos');

        $conflito = $resp->json('conflitos_recursos.0');
        $this->assertSame($sala1['id'], $conflito['recurso_id']);
        $this->assertSame(0, $conflito['disponivel']);
        $this->assertSame(1, $conflito['pedido']);
        $this->assertSame($primeiro['id'], $conflito['eventos'][0]['id']);
        $this->assertSame('Reunião A', $conflito['eventos'][0]['titulo']);

        // encostado: 21h-22h após 19h-21h
        $this->criarEvento($base['anualId'], $base['pastoralB'], [['recurso_id' => $sala1['id']]], [
            'hora_inicio' => '21:00',
            'hora_fim' => '22:00',
        ])->assertCreated();
    }

    public function test_projetor_quantidade_limita_uso_simultaneo(): void
    {
        $base = $this->seedAno();
        $sala1 = $this->criarRecurso(['nome' => 'Sala 1', 'tipo' => 'espaco']);
        $sala2 = $this->criarRecurso(['nome' => 'Sala 2', 'tipo' => 'espaco']);
        $sala3 = $this->criarRecurso(['nome' => 'Sala 3', 'tipo' => 'espaco']);
        $projetor = $this->criarRecurso(['nome' => 'Projetor', 'tipo' => 'recurso', 'quantidade' => 1]);
        $som = $this->criarRecurso(['nome' => 'Caixa de som', 'tipo' => 'recurso', 'quantidade' => 2]);

        $this->criarEvento($base['anualId'], $base['pastoralA'], [
            ['recurso_id' => $sala1['id']],
            ['recurso_id' => $projetor['id'], 'quantidade' => 1],
            ['recurso_id' => $som['id'], 'quantidade' => 1],
        ])->assertCreated();

        $this->criarEvento($base['anualId'], $base['pastoralB'], [
            ['recurso_id' => $sala2['id']],
            ['recurso_id' => $projetor['id'], 'quantidade' => 1],
        ])->assertStatus(422)
            ->assertJsonPath('conflitos_recursos.0.recurso_id', $projetor['id']);

        // projetor em outro dia: ok
        $this->criarEvento($base['anualId'], $base['pastoralB'], [
            ['recurso_id' => $sala2['id']],
            ['recurso_id' => $projetor['id']],
        ], ['data_inicio' => '2026-03-11'])->assertCreated();

        // som tem 2: segundo uso simultâneo ok, terceiro bloqueia
        $this->criarEvento($base['anualId'], $base['pastoralB'], [
            ['recurso_id' => $sala2['id']],
            ['recurso_id' => $som['id'], 'quantidade' => 1],
        ])->assertCreated();

        $this->criarEvento($base['anualId'], $base['pastoralB'], [
            ['recurso_id' => $sala3['id']],
            ['recurso_id' => $som['id'], 'quantidade' => 1],
        ])->assertStatus(422);

        // pedir mais do que existe
        $this->criarEvento($base['anualId'], $base['pastoralB'], [
            ['recurso_id' => $som['id'], 'quantidade' => 3],
        ], ['data_inicio' => '2026-05-01', 'local_texto' => 'Externo'])->assertStatus(422);
    }

    public function test_evento_recusado_nao_ocupa_e_reativar_valida(): void
    {
        $base = $this->seedAno();
        $sala = $this->criarRecurso(['nome' => 'Sala 1', 'tipo' => 'espaco']);

        $recusado = $this->criarEvento($base['anualId'], $base['pastoralA'], [['recurso_id' => $sala['id']]])
            ->assertCreated()->json('data');

        $this->tenantJson('PATCH', '/api/v1/planejamento/eventos/'.$recusado['id'], [
            'status_solicitacao' => 'recusado',
        ])->assertOk();

        $this->criarEvento($base['anualId'], $base['pastoralB'], [['recurso_id' => $sala['id']]])->assertCreated();

        $this->tenantJson('PATCH', '/api/v1/planejamento/eventos/'.$recusado['id'], [
            'status_solicitacao' => 'confirmado',
        ])->assertStatus(422)->assertJsonValidationErrors('recursos');
    }

    public function test_mudar_data_para_horario_ocupado_bloqueia(): void
    {
        $base = $this->seedAno();
        $sala = $this->criarRecurso(['nome' => 'Sala 1', 'tipo' => 'espaco']);

        $this->criarEvento($base['anualId'], $base['pastoralA'], [['recurso_id' => $sala['id']]])->assertCreated();
        $outro = $this->criarEvento($base['anualId'], $base['pastoralB'], [['recurso_id' => $sala['id']]], [
            'data_inicio' => '2026-03-12',
        ])->assertCreated()->json('data');

        $this->tenantJson('PATCH', '/api/v1/planejamento/eventos/'.$outro['id'], [
            'data_inicio' => '2026-03-10',
        ])->assertStatus(422)->assertJsonValidationErrors('recursos');

        $this->tenantJson('PATCH', '/api/v1/planejamento/eventos/'.$outro['id'], [
            'data_inicio' => '2026-03-13',
        ])->assertOk()->assertJsonPath('data.data_inicio', '2026-03-13');

        // salvar o próprio evento sem mudar nada não conflita consigo mesmo
        $this->tenantJson('PATCH', '/api/v1/planejamento/eventos/'.$outro['id'], [
            'titulo' => 'Renomeado',
        ])->assertOk();

        // trocar recursos substitui a alocação
        $this->tenantJson('PATCH', '/api/v1/planejamento/eventos/'.$outro['id'], [
            'recursos' => [],
            'local_texto' => 'Externo',
        ])->assertOk()->assertJsonPath('data.recursos', []);
    }

    public function test_evento_sem_hora_ocupa_dia_inteiro(): void
    {
        $base = $this->seedAno();
        $sala = $this->criarRecurso(['nome' => 'Sala 1', 'tipo' => 'espaco']);

        $this->criarEvento($base['anualId'], $base['pastoralA'], [['recurso_id' => $sala['id']]], [
            'hora_inicio' => null,
            'hora_fim' => null,
        ])->assertCreated();

        $this->criarEvento($base['anualId'], $base['pastoralB'], [['recurso_id' => $sala['id']]], [
            'hora_inicio' => '08:00',
            'hora_fim' => '09:00',
        ])->assertStatus(422);

        $this->criarEvento($base['anualId'], $base['pastoralB'], [['recurso_id' => $sala['id']]], [
            'data_inicio' => '2026-03-08',
            'data_fim' => '2026-03-11',
            'hora_inicio' => null,
            'hora_fim' => null,
        ])->assertStatus(422);
    }

    public function test_espaco_satisfaz_local_e_recurso_invalido_422(): void
    {
        $base = $this->seedAno();
        $projetor = $this->criarRecurso(['nome' => 'Projetor', 'tipo' => 'recurso']);

        // só recurso (sem espaço) e sem local → 422 de local
        $this->criarEvento($base['anualId'], $base['pastoralA'], [['recurso_id' => $projetor['id']]])
            ->assertStatus(422)->assertJsonValidationErrors('local_ids');

        $this->criarEvento($base['anualId'], $base['pastoralA'], [['recurso_id' => '01JXXXXXXXXXXXXXXXXXXXXXXX']], [
            'local_texto' => 'Externo',
        ])->assertStatus(422)->assertJsonValidationErrors('recursos.0.recurso_id');
    }

    public function test_disponibilidade(): void
    {
        $base = $this->seedAno();
        $sala = $this->criarRecurso(['nome' => 'Sala 1', 'tipo' => 'espaco']);
        $som = $this->criarRecurso(['nome' => 'Som', 'tipo' => 'recurso', 'quantidade' => 3]);
        $this->criarRecurso(['nome' => 'Inativo', 'tipo' => 'recurso', 'ativo' => false]);

        $evento = $this->criarEvento($base['anualId'], $base['pastoralA'], [
            ['recurso_id' => $sala['id']],
            ['recurso_id' => $som['id'], 'quantidade' => 2],
        ])->assertCreated()->json('data');

        $url = '/api/v1/planejamento/anuais/'.$base['anualId'].'/disponibilidade';

        $disp = collect($this->tenantJson('GET', $url.'?data_inicio=2026-03-10&hora_inicio=20:00&hora_fim=21:00')
            ->assertOk()->json('data'))->keyBy('recurso_id');
        $this->assertCount(2, $disp);
        $this->assertSame(0, $disp[$sala['id']]['disponivel']);
        $this->assertSame(1, $disp[$som['id']]['disponivel']);
        $this->assertSame(3, $disp[$som['id']]['quantidade']);

        $disp = collect($this->tenantJson('GET', $url.'?data_inicio=2026-03-10&hora_inicio=20:00&hora_fim=21:00&ignorar_evento_id='.$evento['id'])
            ->json('data'))->keyBy('recurso_id');
        $this->assertSame(1, $disp[$sala['id']]['disponivel']);
        $this->assertSame(3, $disp[$som['id']]['disponivel']);

        $this->tenantJson('GET', $url)->assertStatus(422);
    }

    public function test_link_publico_respeita_capacidade_sem_vazar_dados(): void
    {
        $base = $this->seedAno();
        $sala = $this->criarRecurso(['nome' => 'Sala 1', 'tipo' => 'espaco']);

        $this->criarEvento($base['anualId'], $base['pastoralA'], [['recurso_id' => $sala['id']]], [
            'titulo' => 'Segredo da A',
        ])->assertCreated();

        $plain = $this->tenantJson(
            'POST',
            '/api/v1/planejamento/anuais/'.$base['anualId'].'/pastorais/'.$base['pastoralB'].'/token'
        )->assertCreated()->json('token');

        $disp = $this->publicJson('GET', '/api/v1/public/planejamento/'.$plain.'/disponibilidade?data_inicio=2026-03-10&hora_inicio=19:30&hora_fim=20:00')
            ->assertOk()->json('data');
        $this->assertSame(0, $disp[0]['disponivel']);

        $resp = $this->publicJson('POST', '/api/v1/public/planejamento/'.$plain.'/eventos', [
            'titulo' => 'Via link',
            'data_inicio' => '2026-03-10',
            'hora_inicio' => '19:30',
            'hora_fim' => '20:00',
            'recursos' => [['recurso_id' => $sala['id']]],
        ])->assertStatus(422)->assertJsonValidationErrors('recursos');

        $this->assertStringNotContainsString('Segredo da A', $resp->getContent());
        $this->assertArrayNotHasKey('eventos', $resp->json('conflitos_recursos.0'));

        $this->publicJson('POST', '/api/v1/public/planejamento/'.$plain.'/eventos', [
            'titulo' => 'Via link',
            'data_inicio' => '2026-03-11',
            'recursos' => [['recurso_id' => $sala['id']]],
        ])->assertCreated()
            ->assertJsonPath('data.recursos.0.recurso_id', $sala['id']);

        $this->publicJson('POST', '/api/v1/public/planejamento/'.$plain.'/eventos', [
            'titulo' => 'Sem local',
            'data_inicio' => '2026-03-12',
        ])->assertStatus(422);
    }

    public function test_coordenador_aloca_recurso_na_propria_pastoral(): void
    {
        $base = $this->seedAno();
        $sala = $this->criarRecurso(['nome' => 'Sala 1', 'tipo' => 'espaco']);

        $coord = $this->actingAsRole('coordenador-pastoral', 'coord-aloca@test.local');
        tenancy()->initialize($this->tenant);
        Pastoral::query()->findOrFail($base['pastoralA'])->membros()->attach($coord->id, ['papel' => 'coordenador']);
        tenancy()->end();

        Sanctum::actingAs($coord);
        $this->criarEvento($base['anualId'], $base['pastoralA'], [['recurso_id' => $sala['id']]])
            ->assertCreated();

        $this->tenantJson('GET', '/api/v1/planejamento/anuais/'.$base['anualId'].'/disponibilidade?data_inicio=2026-03-10')
            ->assertOk();
    }
}
