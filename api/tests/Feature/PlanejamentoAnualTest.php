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

class PlanejamentoAnualTest extends TestCase
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
            'email' => 'admin-planejamento@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Demo Planejamento',
            'slug' => 'demo-planejamento',
        ]);

        $response->assertCreated();
        $this->tenant = Tenant::query()->where('slug', 'demo-planejamento')->firstOrFail();

        tenancy()->initialize($this->tenant);
        $this->igrejaId = Igreja::query()->firstOrFail()->id;
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
        return $this->withHeaders([
            'X-Tenant' => 'demo-planejamento',
            'X-Igreja' => $this->igrejaId,
        ])->json($method, $uri, $data);
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
     * @return array{anual: array<string, mixed>, pastoral: array<string, mixed>, local: array<string, mixed>, pastoralB: array<string, mixed>}
     */
    private function seedBase(): array
    {
        Sanctum::actingAs($this->admin);

        $local = $this->tenantJson('POST', '/api/v1/calendario/locais', [
            'nome' => 'Salão Paroquial',
            'ordem' => 1,
        ])->assertCreated()->json('data');

        $pastoral = $this->tenantJson('POST', '/api/v1/pastorais', [
            'nome' => 'Pastoral A',
        ])->assertCreated()->json('data');

        $pastoralB = $this->tenantJson('POST', '/api/v1/pastorais', [
            'nome' => 'Pastoral B',
        ])->assertCreated()->json('data');

        $anual = $this->tenantJson('POST', '/api/v1/planejamento/anuais', [
            'ano' => 2026,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'rascunho')
            ->json('data');

        return compact('anual', 'pastoral', 'local', 'pastoralB');
    }

    public function test_nao_autenticado_retorna_401(): void
    {
        Auth::forgetGuards();
        $this->app['auth']->forgetGuards();

        $this->withHeaders([
            'X-Tenant' => 'demo-planejamento',
            'X-Igreja' => $this->igrejaId,
        ])->getJson('/api/v1/planejamento/anuais')->assertUnauthorized();
    }

    public function test_sem_permissao_retorna_403(): void
    {
        $this->actingAsRole('lider-equipe', 'lider-sem-plan@test.local');

        $this->tenantJson('GET', '/api/v1/planejamento/anuais')->assertForbidden();
    }

    public function test_ano_duplicado_422(): void
    {
        $this->seedBase();

        $this->tenantJson('POST', '/api/v1/planejamento/anuais', [
            'ano' => 2026,
        ])->assertStatus(422);
    }

    public function test_transicoes_status_e_pdf(): void
    {
        $base = $this->seedBase();
        $id = $base['anual']['id'];

        $this->tenantJson('POST', '/api/v1/planejamento/anuais/'.$id.'/status', [
            'status' => 'fechado',
        ])->assertStatus(422);

        $this->tenantJson('POST', '/api/v1/planejamento/anuais/'.$id.'/status', [
            'status' => 'coleta',
        ])->assertOk()->assertJsonPath('data.status', 'coleta');

        // Agenda exige mês
        $this->tenantJson('GET', '/api/v1/planejamento/anuais/'.$id.'/pdf?tipo=agenda')
            ->assertStatus(422);

        $agenda = $this->tenantJson('GET', '/api/v1/planejamento/anuais/'.$id.'/pdf?tipo=agenda&mes=3')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $agenda->getContent());

        // Antes do PDF de links, nenhum token gerado
        $tokensAntes = collect(
            $this->tenantJson('GET', '/api/v1/planejamento/anuais/'.$id.'/pastorais/tokens')
                ->assertOk()
                ->json('data')
        )->filter(fn ($t) => ($t['ativo'] ?? false) && ! empty($t['token']));
        $this->assertCount(0, $tokensAntes);

        // PDF links: cria tokens faltantes + tabela Pastoral | QR
        $linksPdf = $this->tenantJson('GET', '/api/v1/planejamento/anuais/'.$id.'/pdf?tipo=links')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $linksPdf->getContent());

        $tokensDepois = collect(
            $this->tenantJson('GET', '/api/v1/planejamento/anuais/'.$id.'/pastorais/tokens')
                ->assertOk()
                ->json('data')
        )->keyBy('pastoral_id');
        $this->assertTrue($tokensDepois[$base['pastoral']['id']]['ativo'] ?? false);
        $this->assertNotEmpty($tokensDepois[$base['pastoral']['id']]['token'] ?? null);
        $this->assertTrue($tokensDepois[$base['pastoralB']['id']]['ativo'] ?? false);
        $this->assertNotEmpty($tokensDepois[$base['pastoralB']['id']]['token'] ?? null);

        $this->tenantJson('POST', '/api/v1/planejamento/anuais/'.$id.'/status', [
            'status' => 'revisao',
        ])->assertOk()->assertJsonPath('data.status', 'revisao');

        $this->tenantJson('GET', '/api/v1/planejamento/anuais/'.$id.'/pdf?tipo=agenda&mes=10')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->tenantJson('POST', '/api/v1/planejamento/anuais/'.$id.'/status', [
            'status' => 'fechado',
        ])->assertOk()->assertJsonPath('data.status', 'fechado');

        $this->tenantJson('POST', '/api/v1/planejamento/anuais/'.$id.'/status', [
            'status' => 'revisao',
        ])->assertOk()->assertJsonPath('data.status', 'revisao');
    }

    public function test_coordenador_escopo_coleta_conflitos_e_fechado(): void
    {
        $base = $this->seedBase();
        $anualId = $base['anual']['id'];
        $pastoralA = $base['pastoral'];
        $pastoralB = $base['pastoralB'];
        $local = $base['local'];

        $coord = $this->actingAsRole('coordenador-pastoral', 'coord-plan@test.local');

        tenancy()->initialize($this->tenant);
        $pastoralModel = \App\Models\Pastoral::query()->findOrFail($pastoralA['id']);
        $pastoralModel->membros()->attach($coord->id, ['papel' => 'coordenador']);
        tenancy()->end();

        Sanctum::actingAs($this->admin);
        $this->tenantJson('POST', '/api/v1/planejamento/anuais/'.$anualId.'/status', [
            'status' => 'coleta',
        ])->assertOk();

        Sanctum::actingAs($coord);

        // Pastoral alheia → 403
        $this->tenantJson('POST', '/api/v1/planejamento/anuais/'.$anualId.'/eventos', [
            'pastoral_id' => $pastoralB['id'],
            'titulo' => 'Evento B',
            'data_inicio' => '2026-03-10',
            'hora_inicio' => '19:00',
            'local_ids' => [$local['id']],
        ])->assertForbidden();

        $eventoA = $this->tenantJson('POST', '/api/v1/planejamento/anuais/'.$anualId.'/eventos', [
            'pastoral_id' => $pastoralA['id'],
            'titulo' => 'Encontro A',
            'data_inicio' => '2026-03-10',
            'hora_inicio' => '19:00',
            'hora_fim' => '21:00',
            'local_ids' => [$local['id']],
        ])->assertCreated()
            ->assertJsonPath('data.status_solicitacao', 'proposta')
            ->json('data');

        // Gestor cria evento conflitante
        Sanctum::actingAs($this->admin);
        $conflito = $this->tenantJson('POST', '/api/v1/planejamento/anuais/'.$anualId.'/eventos', [
            'pastoral_id' => $pastoralB['id'],
            'titulo' => 'Encontro B',
            'data_inicio' => '2026-03-10',
            'hora_inicio' => '20:00',
            'hora_fim' => '22:00',
            'local_ids' => [$local['id']],
        ])->assertCreated()
            ->json('data');

        $this->assertNotEmpty($conflito['conflitos']);
        $this->assertSame($eventoA['id'], $conflito['conflitos'][0]['id']);

        // Evento só com local_texto não gera conflito automático
        $this->tenantJson('POST', '/api/v1/planejamento/anuais/'.$anualId.'/eventos', [
            'pastoral_id' => $pastoralB['id'],
            'titulo' => 'Externo',
            'data_inicio' => '2026-03-10',
            'hora_inicio' => '20:00',
            'local_texto' => 'Casa de retiro',
        ])->assertCreated()
            ->assertJsonPath('data.conflitos', []);

        // Revisão: coordenador só edita se ajuste_solicitado
        $this->tenantJson('POST', '/api/v1/planejamento/anuais/'.$anualId.'/status', [
            'status' => 'revisao',
        ])->assertOk();

        Sanctum::actingAs($coord);
        $this->tenantJson('PATCH', '/api/v1/planejamento/eventos/'.$eventoA['id'], [
            'titulo' => 'Tentativa',
        ])->assertForbidden();

        Sanctum::actingAs($this->admin);
        $this->tenantJson('PATCH', '/api/v1/planejamento/eventos/'.$eventoA['id'], [
            'status_solicitacao' => 'ajuste_solicitado',
            'motivo_ajuste' => 'Mudar horário',
        ])->assertOk();

        Sanctum::actingAs($coord);
        $this->tenantJson('PATCH', '/api/v1/planejamento/eventos/'.$eventoA['id'], [
            'titulo' => 'Encontro A ajustado',
            'hora_inicio' => '18:00',
            'hora_fim' => '20:00',
            'local_ids' => [$local['id']],
        ])->assertOk()
            ->assertJsonPath('data.titulo', 'Encontro A ajustado')
            ->assertJsonPath('data.status_solicitacao', 'proposta');

        Sanctum::actingAs($this->admin);
        $this->tenantJson('POST', '/api/v1/planejamento/anuais/'.$anualId.'/status', [
            'status' => 'fechado',
        ])->assertOk();

        Sanctum::actingAs($coord);
        $this->tenantJson('PATCH', '/api/v1/planejamento/eventos/'.$eventoA['id'], [
            'titulo' => 'Bloqueado',
        ])->assertForbidden();

        $this->tenantJson('POST', '/api/v1/planejamento/anuais/'.$anualId.'/eventos', [
            'pastoral_id' => $pastoralA['id'],
            'titulo' => 'Novo fechado',
            'data_inicio' => '2026-04-01',
            'local_texto' => 'Externo',
        ])->assertForbidden();
    }

    public function test_evento_exige_local_ou_texto(): void
    {
        $base = $this->seedBase();
        $anualId = $base['anual']['id'];

        Sanctum::actingAs($this->admin);
        $this->tenantJson('POST', '/api/v1/planejamento/anuais/'.$anualId.'/status', [
            'status' => 'coleta',
        ])->assertOk();

        $this->tenantJson('POST', '/api/v1/planejamento/anuais/'.$anualId.'/eventos', [
            'pastoral_id' => $base['pastoral']['id'],
            'titulo' => 'Sem local',
            'data_inicio' => '2026-05-01',
        ])->assertStatus(422);
    }

    public function test_isolamento_por_igreja(): void
    {
        $base = $this->seedBase();

        tenancy()->initialize($this->tenant);
        $outra = Igreja::query()->create([
            'nome' => 'Outra Igreja',
            'tipo' => 'paroquia',
        ]);
        $outraId = $outra->id;
        tenancy()->end();

        Sanctum::actingAs($this->admin);

        $listOutra = $this->withHeaders([
            'X-Tenant' => 'demo-planejamento',
            'X-Igreja' => $outraId,
        ])->getJson('/api/v1/planejamento/anuais')
            ->assertOk()
            ->json('data');

        $this->assertCount(0, $listOutra);

        $this->withHeaders([
            'X-Tenant' => 'demo-planejamento',
            'X-Igreja' => $outraId,
        ])->getJson('/api/v1/planejamento/anuais/'.$base['anual']['id'])
            ->assertNotFound();
    }

    public function test_coordenador_ve_global_em_listagem(): void
    {
        $base = $this->seedBase();
        $anualId = $base['anual']['id'];

        Sanctum::actingAs($this->admin);
        $this->tenantJson('POST', '/api/v1/planejamento/anuais/'.$anualId.'/status', [
            'status' => 'coleta',
        ])->assertOk();

        $this->tenantJson('POST', '/api/v1/planejamento/anuais/'.$anualId.'/eventos', [
            'pastoral_id' => $base['pastoralB']['id'],
            'titulo' => 'Global B',
            'data_inicio' => '2026-06-01',
            'local_texto' => 'Rua X',
        ])->assertCreated();

        $coord = $this->actingAsRole('coordenador-pastoral', 'coord-global@test.local');
        tenancy()->initialize($this->tenant);
        \App\Models\Pastoral::query()->findOrFail($base['pastoral']['id'])
            ->membros()->attach($coord->id, ['papel' => 'coordenador']);
        tenancy()->end();

        Sanctum::actingAs($coord);
        $eventos = $this->tenantJson('GET', '/api/v1/planejamento/anuais/'.$anualId.'/eventos')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $eventos);
        $this->assertSame('Global B', $eventos[0]['titulo']);
    }

    public function test_links_pastorais_persistem_e_podem_ser_listados(): void
    {
        $base = $this->seedBase();
        $anualId = $base['anual']['id'];
        $pastoralId = $base['pastoral']['id'];
        $pastoralBId = $base['pastoralB']['id'];

        Sanctum::actingAs($this->admin);
        $plain = $this->tenantJson(
            'POST',
            '/api/v1/planejamento/anuais/'.$anualId.'/pastorais/'.$pastoralId.'/token'
        )->assertCreated()->json('token');

        $this->tenantJson(
            'POST',
            '/api/v1/planejamento/anuais/'.$anualId.'/pastorais/'.$pastoralBId.'/token'
        )->assertCreated();
        $this->tenantJson(
            'DELETE',
            '/api/v1/planejamento/anuais/'.$anualId.'/pastorais/'.$pastoralBId.'/token'
        )->assertNoContent();

        $lista = collect(
            $this->tenantJson('GET', '/api/v1/planejamento/anuais/'.$anualId.'/pastorais/tokens')
                ->assertOk()
                ->json('data')
        )->keyBy('pastoral_id');

        $this->assertSame($plain, $lista[$pastoralId]['token']);
        $this->assertTrue($lista[$pastoralId]['ativo']);
        $this->assertFalse($lista[$pastoralBId]['ativo']);
        $this->assertNull($lista[$pastoralBId]['token']);
        $this->assertArrayNotHasKey('token_hash', $lista[$pastoralId]);

        tenancy()->initialize($this->tenant);
        $raw = \Illuminate\Support\Facades\DB::table('planejamento_pastoral_tokens')
            ->where('pastoral_id', $pastoralId)
            ->value('token_encrypted');
        tenancy()->end();
        $this->assertNotSame($plain, $raw);

        $this->actingAsRole('coordenador-pastoral', 'coord-tokens@test.local');
        $this->tenantJson('GET', '/api/v1/planejamento/anuais/'.$anualId.'/pastorais/tokens')
            ->assertForbidden();
    }

    public function test_link_publico_pastoral_coleta(): void
    {
        $base = $this->seedBase();
        $anualId = $base['anual']['id'];
        $pastoralId = $base['pastoral']['id'];

        Sanctum::actingAs($this->admin);
        $this->tenantJson('POST', '/api/v1/planejamento/anuais/'.$anualId.'/status', [
            'status' => 'coleta',
        ])->assertOk();

        $created = $this->tenantJson(
            'POST',
            '/api/v1/planejamento/anuais/'.$anualId.'/pastorais/'.$pastoralId.'/token'
        )->assertCreated()
            ->json();

        $plain = $created['token'] ?? $created['data']['token'] ?? null;
        $this->assertNotEmpty($plain);

        Auth::forgetGuards();
        $this->app['auth']->forgetGuards();

        $this->withHeader('X-Tenant', 'demo-planejamento')
            ->getJson('/api/v1/public/planejamento/'.$plain)
            ->assertOk()
            ->assertJsonPath('data.pastoral_id', $pastoralId)
            ->assertJsonPath('data.status', 'coleta');

        $this->withHeader('X-Tenant', 'demo-planejamento')
            ->postJson('/api/v1/public/planejamento/'.$plain.'/eventos', [
                'titulo' => 'Via link',
                'data_inicio' => '2026-04-15',
                'hora_inicio' => '19:00',
                'local_texto' => 'Salão',
            ])
            ->assertCreated()
            ->assertJsonPath('data.titulo', 'Via link')
            ->assertJsonPath('data.pastoral_id', $pastoralId)
            ->assertJsonPath('data.status_solicitacao', 'proposta');

        $lista = $this->withHeader('X-Tenant', 'demo-planejamento')
            ->getJson('/api/v1/public/planejamento/'.$plain.'/eventos')
            ->assertOk()
            ->json('data');
        $this->assertCount(1, $lista);

        Sanctum::actingAs($this->admin);
        $this->tenantJson('POST', '/api/v1/planejamento/anuais/'.$anualId.'/status', [
            'status' => 'revisao',
        ])->assertOk();

        Auth::forgetGuards();
        $this->app['auth']->forgetGuards();
        $this->withHeader('X-Tenant', 'demo-planejamento')
            ->postJson('/api/v1/public/planejamento/'.$plain.'/eventos', [
                'titulo' => 'Depois da coleta',
                'data_inicio' => '2026-04-16',
                'local_texto' => 'Salão',
            ])
            ->assertStatus(422);
    }
}
