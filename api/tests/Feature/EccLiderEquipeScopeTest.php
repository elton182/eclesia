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

class EccLiderEquipeScopeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private string $igrejaId;

    private string $equipeAlphaId;

    private string $equipeBetaId;

    private string $casalAlphaId;

    private string $casalBetaId;

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

        $this->casalAlphaId = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'equipe_id' => $this->equipeAlphaId,
            'nome' => 'João Alpha',
            'nome_conjuge' => 'Maria Alpha',
        ])->assertCreated()->json('data.id');

        $this->casalBetaId = $this->tenantJson('POST', '/api/v1/ecc/casais', [
            'equipe_id' => $this->equipeBetaId,
            'nome' => 'Pedro Beta',
            'nome_conjuge' => 'Ana Beta',
        ])->assertCreated()->json('data.id');

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
}
