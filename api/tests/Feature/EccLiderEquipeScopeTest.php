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

class EccLiderEquipeScopeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private string $igrejaId;

    private string $equipeAlphaId;

    private string $equipeBetaId;

    private string $casalAlphaId;

    private string $casalBetaId;

    private string $pessoaAlphaId;

    private string $pessoaBetaId;

    private User $lider;

    protected function setUp(): void
    {
        parent::setUp();

        $platform = SuperAdmin::query()->create([
            'name' => 'Platform',
            'email' => 'platform-lider-scope@test.local',
            'password' => 'password',
        ]);

        Sanctum::actingAs($platform);

        $this->postJson('/api/v1/admin/tenants', [
            'name' => 'Paróquia Escopo',
            'slug' => 'paroquia-escopo',
        ])->assertCreated();

        $this->tenant = Tenant::query()->where('slug', 'paroquia-escopo')->firstOrFail();
        Auth::forgetGuards();

        tenancy()->initialize($this->tenant);
        $admin = User::findByEmail('admin@paroquia-escopo.local');
        $this->igrejaId = Igreja::query()->firstOrFail()->id;
        tenancy()->end();

        Sanctum::actingAs($admin);

        $this->equipeAlphaId = $this->tenantJson('POST', '/api/v1/ecc/equipes', [
            'nome' => 'Equipe Alpha',
            'cor' => '#111111',
        ])->assertCreated()->json('data.id');

        $this->equipeBetaId = $this->tenantJson('POST', '/api/v1/ecc/equipes', [
            'nome' => 'Equipe Beta',
            'cor' => '#222222',
        ])->assertCreated()->json('data.id');

        $casalAlpha = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'equipe_id' => $this->equipeAlphaId,
            'nome' => 'João Alpha',
            'nome_conjuge' => 'Maria Alpha',
        ])->assertCreated()->json('data');
        $this->casalAlphaId = $casalAlpha['id'];
        $this->pessoaAlphaId = $casalAlpha['ele']['id'];

        $casalBeta = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'equipe_id' => $this->equipeBetaId,
            'nome' => 'Pedro Beta',
            'nome_conjuge' => 'Ana Beta',
        ])->assertCreated()->json('data');
        $this->casalBetaId = $casalBeta['id'];
        $this->pessoaBetaId = $casalBeta['ele']['id'];

        $liderId = $this->tenantJson('POST', '/api/v1/users', [
            'name' => 'Líder Alpha',
            'email' => 'lider-alpha@paroquia-escopo.local',
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
        $this->lider = User::findByEmail('lider-alpha@paroquia-escopo.local');
        tenancy()->end();

        Auth::forgetGuards();
        Sanctum::actingAs($this->lider);
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
        return $this->withHeader('X-Tenant', 'paroquia-escopo')->json($method, $uri, $data);
    }

    public function test_lider_lista_apenas_suas_equipes(): void
    {
        $equipes = $this->tenantJson('GET', '/api/v1/ecc/equipes')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $equipes);
        $this->assertSame($this->equipeAlphaId, $equipes[0]['id']);
    }

    public function test_lider_lista_apenas_casais_da_sua_equipe(): void
    {
        $casais = $this->tenantJson('GET', '/api/v1/ecc/casais')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $casais);
        $this->assertSame($this->casalAlphaId, $casais[0]['id']);
        $this->assertSame('João Alpha', $casais[0]['nome']);
        $this->assertSame('Maria Alpha', $casais[0]['nome_conjuge']);
    }

    public function test_lider_nao_ve_casal_de_outra_equipe(): void
    {
        $this->tenantJson('GET', '/api/v1/ecc/casais/'.$this->casalBetaId)
            ->assertNotFound();
    }

    public function test_lider_nao_ve_equipe_alheia(): void
    {
        $this->tenantJson('GET', '/api/v1/ecc/equipes/'.$this->equipeBetaId)
            ->assertNotFound();
    }

    public function test_lider_nao_pode_criar_casal_nem_importar(): void
    {
        $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'equipe_id' => $this->equipeAlphaId,
            'nome' => 'Novo',
            'nome_conjuge' => 'Nova',
        ])->assertForbidden();

        $this->tenantJson('POST', '/api/v1/ecc/casais/import', [
            'rows' => [
                [
                    'equipe' => 'Equipe Alpha',
                    'nome' => 'X',
                    'nome conjuge' => 'Y',
                ],
            ],
        ])->assertForbidden();
    }

    public function test_lider_nao_pode_gerenciar_equipes(): void
    {
        $this->tenantJson('POST', '/api/v1/ecc/equipes', [
            'nome' => 'Equipe Gamma',
        ])->assertForbidden();

        $this->tenantJson('PUT', '/api/v1/ecc/equipes/'.$this->equipeAlphaId, [
            'nome' => 'Alpha Renomeada',
        ])->assertForbidden();

        $this->tenantJson('DELETE', '/api/v1/ecc/equipes/'.$this->equipeAlphaId)
            ->assertForbidden();
    }

    public function test_lider_atualiza_ficha_da_propria_equipe_sem_mover(): void
    {
        $this->tenantJson('PUT', '/api/v1/ecc/casais/'.$this->casalAlphaId, [
            'equipe_id' => $this->equipeBetaId,
            'equipe' => 'Equipe Nova',
            'nome' => 'João Alpha',
            'nome_conjuge' => 'Maria Alpha',
            'telefone' => '11999990000',
            'habilidades' => 'Cozinha',
        ])->assertOk()
            ->assertJsonPath('data.telefone', '11999990000')
            ->assertJsonPath('data.habilidades', 'Cozinha')
            ->assertJsonPath('data.equipe_id', $this->equipeAlphaId);

        $this->tenantJson('GET', '/api/v1/ecc/casais/'.$this->casalAlphaId)
            ->assertOk()
            ->assertJsonPath('data.equipe_id', $this->equipeAlphaId);
    }

    public function test_lider_nao_atualiza_casal_de_outra_equipe_nem_apaga_ou_troca(): void
    {
        $this->tenantJson('PUT', '/api/v1/ecc/casais/'.$this->casalBetaId, [
            'nome' => 'Pedro Beta',
            'nome_conjuge' => 'Ana Beta',
            'telefone' => '11888880000',
        ])->assertNotFound();

        $this->tenantJson('DELETE', '/api/v1/ecc/casais/'.$this->casalAlphaId)
            ->assertForbidden();

        $this->tenantJson('POST', '/api/v1/ecc/casais/'.$this->casalAlphaId.'/swap')
            ->assertForbidden();
    }

    public function test_lider_envia_foto_somente_da_propria_equipe(): void
    {
        $this->withHeader('X-Tenant', 'paroquia-escopo')
            ->post('/api/v1/pessoas/'.$this->pessoaAlphaId.'/foto', [
                'file' => UploadedFile::fake()->image('foto.jpg'),
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $this->pessoaAlphaId);

        $this->withHeader('X-Tenant', 'paroquia-escopo')
            ->post('/api/v1/pessoas/'.$this->pessoaBetaId.'/foto', [
                'file' => UploadedFile::fake()->image('outra.jpg'),
            ])
            ->assertForbidden();

        $this->withHeader('X-Tenant', 'paroquia-escopo')
            ->deleteJson('/api/v1/pessoas/'.$this->pessoaBetaId.'/foto')
            ->assertForbidden();

        $this->withHeader('X-Tenant', 'paroquia-escopo')
            ->deleteJson('/api/v1/pessoas/'.$this->pessoaAlphaId.'/foto')
            ->assertNoContent();
    }
}
