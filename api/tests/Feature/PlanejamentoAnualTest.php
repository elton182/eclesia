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
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PlanejamentoAnualTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private SuperAdmin $admin;

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
        return $this->withHeader('X-Tenant', 'demo-planejamento')->json($method, $uri, $data);
    }

    private function igrejaId(): string
    {
        tenancy()->initialize($this->tenant);
        $id = Igreja::query()->firstOrFail()->id;
        tenancy()->end();

        return $id;
    }

    /**
     * @return array{id: string, nome: string}
     */
    private function criarPastoral(string $nome = 'Pastoral Família'): array
    {
        return $this->tenantJson('POST', '/api/v1/site/pastorais', [
            'igreja_id' => $this->igrejaId(),
            'nome' => $nome,
            'ativa' => true,
        ])->assertCreated()->json('data');
    }

    public function test_fluxo_criar_coleta_token_publico_aprovar_fechar(): void
    {
        $pastoral = $this->criarPastoral();

        $ano = $this->tenantJson('POST', '/api/v1/planejamento/anos', [
            'ano' => 2026,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'aberto')
            ->assertJsonPath('data.ano', 2026)
            ->json('data');

        $this->assertNotEmpty($ano['aberto_em']);

        $this->tenantJson('POST', '/api/v1/planejamento/anos/'.$ano['id'].'/status', [
            'status' => 'coleta',
        ])->assertOk()
            ->assertJsonPath('data.status', 'coleta');

        $tokenResp = $this->tenantJson(
            'POST',
            '/api/v1/planejamento/anos/'.$ano['id'].'/pastorais/'.$pastoral['id'].'/token'
        )->assertCreated()->json();

        $token = $tokenResp['token'];
        $this->assertNotEmpty($token);
        $this->assertSame($token, $tokenResp['data']['token'] ?? null);

        Auth::forgetGuards();

        $eventoPublico = $this->withHeader('X-Tenant', 'demo-planejamento')
            ->postJson('/api/v1/public/planejamento/'.$token.'/eventos', [
                'titulo' => 'Retiro de casais',
                'descricao' => 'Proposta pastoral',
                'data_inicio' => '2026-05-10T09:00:00',
                'dia_inteiro' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'proposta')
            ->assertJsonPath('data.criado_via', 'link')
            ->assertJsonPath('data.pastoral_id', $pastoral['id'])
            ->json('data');

        Sanctum::actingAs($this->admin);

        $this->tenantJson('PATCH', '/api/v1/planejamento/eventos/'.$eventoPublico['id'], [
            'status' => 'aprovado',
        ])->assertOk()
            ->assertJsonPath('data.status', 'aprovado');

        $this->tenantJson('PATCH', '/api/v1/planejamento/eventos/'.$eventoPublico['id'], [
            'data_inicio' => '2026-05-17T09:00:00',
        ])->assertOk()
            ->assertJsonPath('data.data_inicio', fn ($v) => str_contains((string) $v, '2026-05-17'));

        $this->tenantJson('POST', '/api/v1/planejamento/anos/'.$ano['id'].'/status', [
            'status' => 'fechado',
        ])->assertOk()
            ->assertJsonPath('data.status', 'fechado');

        Auth::forgetGuards();

        $this->withHeader('X-Tenant', 'demo-planejamento')
            ->postJson('/api/v1/public/planejamento/'.$token.'/eventos', [
                'titulo' => 'Após fechamento',
                'data_inicio' => '2026-06-01T10:00:00',
            ])
            ->assertStatus(422);

        Sanctum::actingAs($this->admin);

        $this->tenantJson('PATCH', '/api/v1/planejamento/eventos/'.$eventoPublico['id'], [
            'data_inicio' => '2026-05-20T09:00:00',
        ])->assertStatus(422);
    }

    public function test_propor_com_vinculo_e_sem_vinculo(): void
    {
        $pastoral = $this->criarPastoral();
        $outra = $this->criarPastoral('Pastoral Liturgia');

        $ano = $this->tenantJson('POST', '/api/v1/planejamento/anos', [
            'ano' => 2027,
        ])->assertCreated()->json('data');

        $this->tenantJson('POST', '/api/v1/planejamento/anos/'.$ano['id'].'/status', [
            'status' => 'coleta',
        ])->assertOk();

        tenancy()->initialize($this->tenant);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $igrejaId = Igreja::query()->firstOrFail()->id;
        setPermissionsTeamId($igrejaId);

        $user = User::query()->create([
            'name' => 'Proponente',
            'email' => 'propor@demo-planejamento.local',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->givePermissionTo(['planejamento.propor', 'telas.planejamento']);
        $userUlid = $user->ulid;
        setPermissionsTeamId(null);
        tenancy()->end();

        Sanctum::actingAs($user);

        $this->tenantJson('POST', '/api/v1/planejamento/anos/'.$ano['id'].'/eventos', [
            'pastoral_id' => $pastoral['id'],
            'titulo' => 'Sem vínculo',
            'data_inicio' => '2027-03-01T08:00:00',
        ])->assertStatus(422);

        Sanctum::actingAs($this->admin);

        $this->tenantJson('POST', '/api/v1/planejamento/pastorais/'.$pastoral['id'].'/usuarios', [
            'user_id' => $userUlid,
        ])->assertCreated();

        Sanctum::actingAs($user);

        $evento = $this->tenantJson('POST', '/api/v1/planejamento/anos/'.$ano['id'].'/eventos', [
            'pastoral_id' => $pastoral['id'],
            'titulo' => 'Com vínculo',
            'data_inicio' => '2027-03-01T08:00:00',
        ])->assertCreated()
            ->assertJsonPath('data.criado_via', 'login')
            ->assertJsonPath('data.status', 'proposta')
            ->json('data');

        $this->tenantJson('POST', '/api/v1/planejamento/anos/'.$ano['id'].'/eventos', [
            'pastoral_id' => $outra['id'],
            'titulo' => 'Outra pastoral',
            'data_inicio' => '2027-04-01T08:00:00',
        ])->assertStatus(422);

        $this->tenantJson('PUT', '/api/v1/planejamento/eventos/'.$evento['id'], [
            'titulo' => 'Atualizado',
        ])->assertOk()
            ->assertJsonPath('data.titulo', 'Atualizado');
    }

    public function test_planejamento_requer_auth(): void
    {
        Auth::forgetGuards();

        $this->withHeader('X-Tenant', 'demo-planejamento')
            ->getJson('/api/v1/planejamento/anos')
            ->assertUnauthorized();
    }

    public function test_status_invalido_retorna_422(): void
    {
        $ano = $this->tenantJson('POST', '/api/v1/planejamento/anos', [
            'ano' => 2028,
        ])->assertCreated()->json('data');

        $this->tenantJson('POST', '/api/v1/planejamento/anos/'.$ano['id'].'/status', [
            'status' => 'montagem',
        ])->assertStatus(422);

        $this->tenantJson('POST', '/api/v1/planejamento/anos/'.$ano['id'].'/status', [
            'status' => 'fechado',
        ])->assertStatus(422);
    }
}
